package main

import (
	"errors"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"
)

func TestUpdateAttemptIDReadsAndTrimsHeader(t *testing.T) {
	request := httptest.NewRequest("POST", "/update", nil)
	request.Header.Set("X-Update-Attempt-ID", " attempt-123 ")

	if actual := updateAttemptID(request); actual != "attempt-123" {
		t.Fatalf("expected update attempt ID to be preserved, got %q", actual)
	}
}

func TestNewUpdateAttemptIDReturnsUUID(t *testing.T) {
	attemptID := newUpdateAttemptID()

	if len(attemptID) != 36 || strings.Count(attemptID, "-") != 4 {
		t.Fatalf("expected generated update attempt UUID, got %q", attemptID)
	}
}

func TestStateForAttemptRetainsTerminalResultAfterNextAttemptStarts(t *testing.T) {
	completedAt := time.Date(2026, time.September, 10, 12, 0, 0, 0, time.UTC)
	server := Server{
		config: Config{StatePath: filepath.Join(t.TempDir(), "state.json")},
		state:  State{Logs: []LogEntry{}},
	}
	server.recordAttemptResultState("attempt-a", "success", false, completedAt)
	server.recordAttemptResultState("attempt-failed", "failed", false, completedAt.Add(time.Second))
	server.saveState()

	server.state = server.loadState()
	server.state.LastUpdateState = "running"
	server.state.UpdateAttemptID = "attempt-b"
	server.state.UpdateRunning = true

	for attemptID, expectedState := range map[string]string{
		"attempt-a":      "success",
		"attempt-failed": "failed",
	} {
		state := server.stateForAttempt(attemptID)

		if state.UpdateAttemptID != attemptID || state.LastUpdateState != expectedState || state.UpdateRunning {
			t.Fatalf("expected terminal state %q for %s, got %#v", expectedState, attemptID, state)
		}

		if state.AttemptResults != nil {
			t.Fatalf("expected retained attempt index to remain private")
		}
	}
}

func TestAttemptStatusUsesStoredStateWithoutInspectingImages(t *testing.T) {
	originalCommandOutputFunc := commandOutputFunc
	defer func() {
		commandOutputFunc = originalCommandOutputFunc
	}()

	commandOutputFunc = func(workdir string, name string, args ...string) ([]byte, error) {
		t.Fatalf("expected attempt status to avoid Compose commands")
		return nil, nil
	}

	server := Server{
		state: State{
			LastUpdateState: "success",
			UpdateAttemptID: "attempt-123",
		},
	}
	request := httptest.NewRequest("GET", "/status?attempt_id=attempt-123", nil)
	response := httptest.NewRecorder()

	server.status(response, request)

	if response.Code != 200 {
		t.Fatalf("expected successful attempt status response, got %d", response.Code)
	}
	if !strings.Contains(response.Body.String(), `"update_attempt_id":"attempt-123"`) {
		t.Fatalf("expected stored attempt status, got %s", response.Body.String())
	}
}

func TestImageStateMarksSelfServiceAsAutomaticWhenSelfUpdateEnabled(t *testing.T) {
	server := Server{
		config: Config{
			RuntimeServices: []string{"app", "horizon"},
			SelfService:     "system-updater",
			SelfUpdate:      true,
		},
	}

	image := server.imageState("system-updater", "example/updater:latest", "sha256:current", "sha256:available")

	if !image.UpdateAvailable {
		t.Fatalf("expected self-service update to be available")
	}

	if image.ManualUpdateRequired {
		t.Fatalf("expected self-service update to be eligible for automatic updates")
	}
}

func TestImageStateKeepsSelfServiceManualWhenSelfUpdateDisabled(t *testing.T) {
	server := Server{
		config: Config{
			RuntimeServices: []string{"app", "horizon"},
			SelfService:     "system-updater",
			SelfUpdate:      false,
		},
	}

	image := server.imageState("system-updater", "example/updater:latest", "sha256:current", "sha256:available")

	if !image.ManualUpdateRequired {
		t.Fatalf("expected self-service update to require manual handling when self-update is disabled")
	}
}

func TestAnyUpdateAvailableIncludesSelfServiceUpdateWhenAutomatic(t *testing.T) {
	server := Server{
		config: Config{
			RuntimeServices: []string{"app", "horizon"},
			SelfService:     "system-updater",
			SelfUpdate:      true,
		},
	}

	images := []ImageState{
		server.imageState("system-updater", "example/updater:latest", "sha256:current", "sha256:available"),
	}

	if !anyUpdateAvailable(images) {
		t.Fatalf("expected self-service update to mark update availability when self-update is enabled")
	}
}

func TestRuntimeUpdateArgsForceRecreatesAllRuntimeServices(t *testing.T) {
	server := Server{
		config: Config{RuntimeServices: []string{"app", "horizon", "nginx"}},
	}

	actual := strings.Join(server.runtimeUpdateArgs(), " ")
	expected := "up -d --no-deps --force-recreate app horizon nginx"

	if actual != expected {
		t.Fatalf("expected runtime services to be force-recreated, got %q", actual)
	}
}

func TestSelfUpdateUsesIndependentComposeHelper(t *testing.T) {
	server := Server{
		config: Config{
			ComposeEnvFile: "/workspace/.env",
			ComposeFiles: []string{
				"/workspace/docker-compose.prod.yml",
				"/workspace/docker-compose.registry.yml",
			},
			ProjectName: "core-panel",
			SelfService: "system-updater",
			StatePath:   "/data/state.json",
			Workdir:     "/workspace",
		},
	}

	actual := strings.Join(server.selfUpdateHelperArgs("attempt-123"), " ")
	expected := "run --rm --no-deps -T --entrypoint system-updater system-updater self-update-helper /data/state.json attempt-123 /workspace compose --env-file /workspace/.env -p core-panel -f /workspace/docker-compose.prod.yml -f /workspace/docker-compose.registry.yml up -d --no-deps system-updater"

	if actual != expected {
		t.Fatalf("expected self-update to run through an independent Compose helper, got %q", actual)
	}
}

func TestRunUpdateMarksStateFailedWhenSelfUpdateFails(t *testing.T) {
	originalCommandOutputFunc := commandOutputFunc
	defer func() {
		commandOutputFunc = originalCommandOutputFunc
	}()

	var server *Server

	commandOutputFunc = func(workdir string, name string, args ...string) ([]byte, error) {
		command := strings.Join(append([]string{name}, args...), " ")

		switch command {
		case "docker compose -p core-panel -f docker-compose.yml pull":
			return []byte{}, nil
		case "docker compose -p core-panel -f docker-compose.yml up -d --no-deps --force-recreate app":
			return []byte{}, nil
		case "docker compose -p core-panel -f docker-compose.yml config --format json":
			return []byte(`{"services":{}}`), nil
		case "docker compose -p core-panel -f docker-compose.yml run --rm --no-deps -T --entrypoint system-updater system-updater self-update-helper " + server.config.StatePath + " attempt-123 " + server.config.Workdir + " compose -p core-panel -f docker-compose.yml up -d --no-deps system-updater":
			if !server.state.UpdateRunning || server.state.LastUpdateState != "running" || !server.state.SelfUpdatePending {
				t.Fatalf("expected self-update to remain nonterminal while Compose is running, got %#v", server.state)
			}

			if _, exists := server.state.AttemptResults["attempt-123"]; exists {
				t.Fatalf("expected no terminal attempt result before self-update completes")
			}

			return nil, errors.New("self-update failed")
		default:
			t.Fatalf("unexpected command: %s", command)
			return nil, nil
		}
	}

	server = &Server{
		config: Config{
			ComposeFiles:    []string{"docker-compose.yml"},
			ProjectName:     "core-panel",
			RuntimeServices: []string{"app"},
			SelfService:     "system-updater",
			SelfUpdate:      true,
			StatePath:       filepath.Join(t.TempDir(), "state.json"),
		},
		state: State{
			LastUpdateState: "running",
			Logs:            []LogEntry{},
			UpdateAttemptID: "attempt-123",
			UpdateRunning:   true,
		},
	}

	server.runUpdate("attempt-123")

	if server.state.LastUpdateState != "failed" {
		t.Fatalf("expected self-update failure to mark last update state as failed, got %q", server.state.LastUpdateState)
	}

	if server.state.UpdateRunning {
		t.Fatalf("expected update running flag to be cleared")
	}

	if len(server.state.Logs) == 0 || !strings.Contains(server.state.Logs[0].Message, "updater service update failed") {
		t.Fatalf("expected self-update failure to be logged")
	}

	if result := server.state.AttemptResults["attempt-123"]; result.LastUpdateState != "failed" {
		t.Fatalf("expected self-update failure to be retained for the attempt, got %#v", result)
	}
}

func TestLoadStateCompletesPendingSelfUpdateAfterServiceRestart(t *testing.T) {
	startedAt := time.Date(2026, time.September, 11, 12, 0, 0, 0, time.UTC)
	server := &Server{
		config: Config{StatePath: filepath.Join(t.TempDir(), "state.json")},
		state: State{
			LastUpdateAt:      &startedAt,
			LastUpdateState:   "running",
			Logs:              []LogEntry{},
			SelfUpdatePending: true,
			UpdateAttemptID:   "attempt-123",
			UpdateRunning:     true,
		},
	}
	server.saveState()
	if err := writeSelfUpdateCompletion(server.config.StatePath, "attempt-123"); err != nil {
		t.Fatalf("could not write self-update completion marker: %v", err)
	}

	state := server.loadState()

	if state.UpdateRunning || state.SelfUpdatePending || state.LastUpdateState != "success" {
		t.Fatalf("expected pending self-update to complete after service restart, got %#v", state)
	}

	if result := state.AttemptResults["attempt-123"]; result.LastUpdateState != "success" || result.UpdateRunning {
		t.Fatalf("expected restarted updater to retain terminal success, got %#v", result)
	}
}

func TestLoadStateDoesNotCompletePendingSelfUpdateWithoutConfirmation(t *testing.T) {
	startedAt := time.Now().UTC()
	server := &Server{
		config: Config{StatePath: filepath.Join(t.TempDir(), "state.json")},
		state: State{
			LastUpdateAt:      &startedAt,
			LastUpdateState:   "running",
			Logs:              []LogEntry{},
			SelfUpdatePending: true,
			UpdateAttemptID:   "attempt-123",
			UpdateRunning:     true,
		},
	}
	server.saveState()

	state := server.loadState()

	if !state.UpdateRunning || !state.SelfUpdatePending || state.LastUpdateState != "running" {
		t.Fatalf("expected unconfirmed self-update to remain pending, got %#v", state)
	}
}

func TestLoadStateFailsExpiredUnconfirmedSelfUpdate(t *testing.T) {
	startedAt := time.Now().UTC().Add(-selfUpdateConfirmationTTL - time.Second)
	server := &Server{
		config: Config{StatePath: filepath.Join(t.TempDir(), "state.json")},
		state: State{
			LastUpdateAt:      &startedAt,
			LastUpdateState:   "running",
			Logs:              []LogEntry{},
			SelfUpdatePending: true,
			UpdateAttemptID:   "attempt-123",
			UpdateRunning:     true,
		},
	}
	server.saveState()

	state := server.loadState()

	if state.UpdateRunning || state.SelfUpdatePending || state.LastUpdateState != "failed" {
		t.Fatalf("expected expired unconfirmed self-update to fail, got %#v", state)
	}
}

func TestLoadStateKeepsExpiredSelfUpdateRunningWhileHelperHeartbeatIsFresh(t *testing.T) {
	startedAt := time.Now().UTC().Add(-selfUpdateConfirmationTTL - time.Second)
	server := &Server{
		config: Config{StatePath: filepath.Join(t.TempDir(), "state.json")},
		state: State{
			LastUpdateAt:      &startedAt,
			LastUpdateState:   "running",
			Logs:              []LogEntry{},
			SelfUpdatePending: true,
			UpdateAttemptID:   "attempt-123",
			UpdateRunning:     true,
		},
	}
	server.saveState()
	if err := writeSelfUpdateHeartbeat(server.config.StatePath, "attempt-123"); err != nil {
		t.Fatalf("could not write self-update heartbeat: %v", err)
	}

	state := server.loadState()

	if !state.UpdateRunning || !state.SelfUpdatePending || state.LastUpdateState != "running" {
		t.Fatalf("expected live self-update helper to remain running, got %#v", state)
	}
}

func TestSelfUpdateHelperWritesCompletionAfterComposeSucceeds(t *testing.T) {
	originalCommandOutputFunc := commandOutputFunc
	defer func() {
		commandOutputFunc = originalCommandOutputFunc
	}()

	statePath := filepath.Join(t.TempDir(), "state.json")
	commandOutputFunc = func(workdir string, name string, args ...string) ([]byte, error) {
		if workdir != "/workspace" || name != "docker" || strings.Join(args, " ") != "compose up -d system-updater" {
			t.Fatalf("unexpected helper command: %s %s in %s", name, strings.Join(args, " "), workdir)
		}
		heartbeat, err := os.ReadFile(selfUpdateHeartbeatPath(statePath))
		if err != nil || strings.TrimSpace(string(heartbeat)) != "attempt-123" {
			t.Fatalf("expected matching heartbeat before Compose starts, got %q (%v)", heartbeat, err)
		}

		return []byte{}, nil
	}

	if err := runSelfUpdateHelper([]string{statePath, "attempt-123", "/workspace", "compose", "up", "-d", "system-updater"}); err != nil {
		t.Fatalf("expected helper to succeed: %v", err)
	}

	server := Server{config: Config{StatePath: statePath}}
	completionAttemptID, err := server.readSelfUpdateCompletion()
	if err != nil || completionAttemptID != "attempt-123" {
		t.Fatalf("expected matching completion marker, got %q (%v)", completionAttemptID, err)
	}
	if _, err := os.Stat(selfUpdateHeartbeatPath(statePath)); !errors.Is(err, os.ErrNotExist) {
		t.Fatalf("expected helper heartbeat to be removed after success, got %v", err)
	}
}

func TestSelfUpdateHelperDoesNotConfirmFailedCompose(t *testing.T) {
	originalCommandOutputFunc := commandOutputFunc
	defer func() {
		commandOutputFunc = originalCommandOutputFunc
	}()

	statePath := filepath.Join(t.TempDir(), "state.json")
	commandOutputFunc = func(workdir string, name string, args ...string) ([]byte, error) {
		return nil, errors.New("compose failed")
	}

	if err := runSelfUpdateHelper([]string{statePath, "attempt-123", "/workspace", "compose", "up"}); err == nil {
		t.Fatalf("expected helper failure")
	}

	server := Server{config: Config{StatePath: statePath}}
	if completionAttemptID, err := server.readSelfUpdateCompletion(); err == nil {
		t.Fatalf("expected no completion marker after failure, got %q", completionAttemptID)
	}
	if _, err := os.Stat(selfUpdateHeartbeatPath(statePath)); !errors.Is(err, os.ErrNotExist) {
		t.Fatalf("expected helper heartbeat to be removed after failure, got %v", err)
	}
}
