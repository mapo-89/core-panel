package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
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

func TestStatusReturnsCachedStateWithoutInspectingImages(t *testing.T) {
	originalCommandOutputFunc := commandOutputFunc
	defer func() {
		commandOutputFunc = originalCommandOutputFunc
	}()

	commandCalled := false
	commandOutputFunc = func(workdir string, name string, args ...string) ([]byte, error) {
		commandCalled = true

		return nil, errors.New("status must not run external commands")
	}

	server := &Server{
		config: Config{StatePath: filepath.Join(t.TempDir(), "state.json")},
		state: State{
			Images:          []ImageState{{Image: "app:test", Service: "app"}},
			Logs:            []LogEntry{},
			UpdateAvailable: true,
		},
	}
	response := httptest.NewRecorder()

	server.status(response, httptest.NewRequest("GET", "/status", nil))

	if commandCalled {
		t.Fatal("expected status to return cached state without inspecting images")
	}
	if response.Code != 200 || !strings.Contains(response.Body.String(), `"service":"app"`) {
		t.Fatalf("expected cached status response, got %d: %s", response.Code, response.Body.String())
	}
}

func TestCheckPublishesStartLogBeforePullCompletes(t *testing.T) {
	originalCommandOutputFunc := commandOutputFunc
	defer func() {
		commandOutputFunc = originalCommandOutputFunc
	}()

	pullStarted := make(chan struct{})
	releasePull := make(chan struct{})
	commandOutputFunc = func(workdir string, name string, args ...string) ([]byte, error) {
		command := strings.Join(args, " ")

		if strings.HasSuffix(command, " pull") {
			close(pullStarted)
			<-releasePull

			return nil, nil
		}

		if strings.HasSuffix(command, " config --format json") {
			return []byte(`{"services":{}}`), nil
		}

		return nil, errors.New("unexpected command: " + command)
	}

	server := &Server{
		config: Config{
			ProjectName: "core-panel",
			StatePath:   filepath.Join(t.TempDir(), "state.json"),
			Workdir:     t.TempDir(),
		},
		state: State{Logs: []LogEntry{}},
	}
	checkResponse := httptest.NewRecorder()
	checkDone := make(chan struct{})

	go func() {
		server.check(checkResponse, httptest.NewRequest("POST", "/check", nil))
		close(checkDone)
	}()

	select {
	case <-pullStarted:
	case <-time.After(time.Second):
		t.Fatal("timed out waiting for image pull to start")
	}

	logsResponse := httptest.NewRecorder()
	logsDone := make(chan struct{})
	go func() {
		server.logs(logsResponse, httptest.NewRequest("GET", "/logs", nil))
		close(logsDone)
	}()

	select {
	case <-logsDone:
	case <-time.After(time.Second):
		t.Fatal("logs remained blocked while the image pull was running")
	}

	if body := logsResponse.Body.String(); !strings.Contains(body, "checking for image updates") || strings.Contains(body, "image update check completed") {
		t.Fatalf("expected only the running check log before pull completion, got %s", body)
	}

	persistedState := server.loadState()
	if len(persistedState.Logs) == 0 || persistedState.Logs[0].Message != "checking for image updates" {
		t.Fatalf("expected running check log to be persisted immediately, got %#v", persistedState.Logs)
	}

	close(releasePull)
	select {
	case <-checkDone:
	case <-time.After(time.Second):
		t.Fatal("timed out waiting for image check to complete")
	}

	if checkResponse.Code != 200 {
		t.Fatalf("expected successful image check response, got %d: %s", checkResponse.Code, checkResponse.Body.String())
	}
	if !strings.Contains(checkResponse.Body.String(), "image update check completed") {
		t.Fatalf("expected completion log after successful check, got %s", checkResponse.Body.String())
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

func TestCollectImagesDeduplicatesServicesUsingTheSameImage(t *testing.T) {
	originalCommandOutputFunc := commandOutputFunc
	defer func() {
		commandOutputFunc = originalCommandOutputFunc
	}()

	commandOutputFunc = func(workdir string, name string, args ...string) ([]byte, error) {
		command := strings.Join(append([]string{name}, args...), " ")

		switch command {
		case "docker compose -p core-panel -f docker-compose.yml config --format json":
			return []byte(`{"services":{"app":{"image":"example/app:latest"},"horizon":{"image":"example/app:latest"},"scheduler":{"image":"example/app:latest"}}}`), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q app":
			return []byte("app-container\n"), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q horizon":
			return []byte("horizon-container\n"), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q scheduler":
			return []byte("scheduler-container\n"), nil
		case "docker inspect --format {{.Image}} app-container",
			"docker inspect --format {{.Image}} horizon-container",
			"docker inspect --format {{.Image}} scheduler-container":
			return []byte("sha256:current\n"), nil
		case "docker image inspect --format {{.Id}} example/app:latest":
			return []byte("sha256:available\n"), nil
		default:
			t.Fatalf("unexpected command: %s", command)
			return nil, nil
		}
	}

	server := Server{
		config: Config{
			ComposeFiles:    []string{"docker-compose.yml"},
			ProjectName:     "core-panel",
			RuntimeServices: []string{"app", "horizon", "scheduler"},
		},
	}

	images, err := server.collectImages()
	if err != nil {
		t.Fatalf("expected image collection to succeed: %v", err)
	}

	if len(images) != 1 {
		t.Fatalf("expected one deduplicated image state, got %#v", images)
	}

	image := images[0]
	if image.Service != "app" || strings.Join(image.Services, ",") != "app,horizon,scheduler" {
		t.Fatalf("expected all application services to share one image state, got %#v", image)
	}

	if !image.UpdateAvailable || image.ManualUpdateRequired {
		t.Fatalf("expected the shared runtime image update to remain automatic, got %#v", image)
	}
}

func TestCollectImagesSeparatesSharedImageServicesByUpdateEligibility(t *testing.T) {
	originalCommandOutputFunc := commandOutputFunc
	defer func() {
		commandOutputFunc = originalCommandOutputFunc
	}()
	runtimeDigest := "sha256:stale\n"

	commandOutputFunc = func(workdir string, name string, args ...string) ([]byte, error) {
		command := strings.Join(append([]string{name}, args...), " ")

		switch command {
		case "docker compose -p core-panel -f docker-compose.yml config --format json":
			return []byte(`{"services":{"aaa-custom":{"image":"example/app:latest"},"app":{"image":"example/app:latest"},"horizon":{"image":"example/app:latest"},"zzz-custom":{"image":"example/app:latest"}}}`), nil
		case "docker image inspect --format {{.Id}} example/app:latest":
			return []byte("sha256:available\n"), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q aaa-custom":
			return []byte("aaa-custom-container\n"), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q app":
			return []byte("app-container\n"), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q horizon":
			return []byte("horizon-container\n"), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q zzz-custom":
			return []byte("zzz-custom-container\n"), nil
		case "docker inspect --format {{.Image}} aaa-custom-container",
			"docker inspect --format {{.Image}} zzz-custom-container":
			return []byte("sha256:stale\n"), nil
		case "docker inspect --format {{.Image}} app-container",
			"docker inspect --format {{.Image}} horizon-container":
			return []byte(runtimeDigest), nil
		default:
			t.Fatalf("unexpected command: %s", command)
			return nil, nil
		}
	}

	server := Server{
		config: Config{
			ComposeFiles:    []string{"docker-compose.yml"},
			ProjectName:     "core-panel",
			RuntimeServices: []string{"app", "horizon"},
		},
	}

	images, err := server.collectImages()
	if err != nil {
		t.Fatalf("expected image collection to succeed: %v", err)
	}

	if len(images) != 2 {
		t.Fatalf("expected automatic and manual image states, got %#v", images)
	}

	automatic := images[0]
	manual := images[1]
	if automatic.ManualUpdateRequired || strings.Join(automatic.Services, ",") != "app,horizon" || !automatic.UpdateAvailable {
		t.Fatalf("expected runtime services to retain automatic update eligibility, got %#v", automatic)
	}
	if !manual.ManualUpdateRequired || strings.Join(manual.Services, ",") != "aaa-custom,zzz-custom" || !manual.UpdateAvailable {
		t.Fatalf("expected custom services to remain a separate manual update group, got %#v", manual)
	}
	if !anyUpdateAvailable(images) {
		t.Fatalf("expected the eligible runtime image group to keep automatic updates available")
	}

	runtimeDigest = "sha256:available\n"
	images, err = server.collectImages()
	if err != nil {
		t.Fatalf("expected image collection with current runtime services to succeed: %v", err)
	}

	automatic = images[0]
	manual = images[1]
	if automatic.UpdateAvailable || automatic.ManualUpdateRequired {
		t.Fatalf("expected current runtime services not to inherit a custom service update, got %#v", automatic)
	}
	if !manual.UpdateAvailable || !manual.ManualUpdateRequired {
		t.Fatalf("expected stale custom services to keep their manual update, got %#v", manual)
	}
	if anyUpdateAvailable(images) {
		t.Fatalf("expected a manual-only custom service update not to trigger automatic runtime updates")
	}
}

func TestCollectImagesDetectsAStaleServiceUsingADeduplicatedImage(t *testing.T) {
	originalCommandOutputFunc := commandOutputFunc
	defer func() {
		commandOutputFunc = originalCommandOutputFunc
	}()

	commandOutputFunc = func(workdir string, name string, args ...string) ([]byte, error) {
		command := strings.Join(append([]string{name}, args...), " ")

		switch command {
		case "docker compose -p core-panel -f docker-compose.yml config --format json":
			return []byte(`{"services":{"app":{"image":"example/app:latest"},"horizon":{"image":"example/app:latest"},"scheduler":{"image":"example/app:latest"}}}`), nil
		case "docker image inspect --format {{.Id}} example/app:latest":
			return []byte("sha256:available\n"), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q app":
			return []byte("app-container\n"), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q horizon":
			return []byte("horizon-container\n"), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q scheduler":
			return []byte("scheduler-container\n"), nil
		case "docker inspect --format {{.Image}} app-container",
			"docker inspect --format {{.Image}} scheduler-container":
			return []byte("sha256:available\n"), nil
		case "docker inspect --format {{.Image}} horizon-container":
			return []byte("sha256:stale\n"), nil
		default:
			t.Fatalf("unexpected command: %s", command)
			return nil, nil
		}
	}

	server := Server{
		config: Config{
			ComposeFiles:    []string{"docker-compose.yml"},
			ProjectName:     "core-panel",
			RuntimeServices: []string{"app", "horizon", "scheduler"},
		},
	}

	images, err := server.collectImages()
	if err != nil {
		t.Fatalf("expected image collection to succeed: %v", err)
	}

	if len(images) != 1 {
		t.Fatalf("expected one deduplicated image state, got %#v", images)
	}

	image := images[0]
	if image.CurrentDigest != "sha256:stale" || !image.UpdateAvailable || image.ManualUpdateRequired {
		t.Fatalf("expected the stale shared-image service to keep the automatic update available, got %#v", image)
	}
}

func TestRuntimeUpdateArgsForceRecreatesAllRuntimeServices(t *testing.T) {
	server := Server{
		config: Config{RuntimeServices: []string{"app", "horizon", "scheduler"}},
	}

	actual := strings.Join(server.runtimeUpdateArgs(), " ")
	expected := "up -d --no-deps --force-recreate app horizon scheduler"

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

	actual := strings.Join(server.selfUpdateHelperArgs("attempt-123", "sha256:new"), " ")
	expected := "run --detach --pull never --rm --no-deps -T --entrypoint system-updater system-updater self-update-helper /data/state.json attempt-123 /workspace system-updater sha256:new compose --env-file /workspace/.env -p core-panel -f /workspace/docker-compose.prod.yml -f /workspace/docker-compose.registry.yml"

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
			return []byte(`{"services":{"system-updater":{"image":"updater:staging"}}}`), nil
		case "docker image inspect --format {{.Id}} updater:staging":
			return []byte("sha256:new"), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q system-updater":
			return nil, nil
		case "docker compose -p core-panel -f docker-compose.yml run --detach --pull never --rm --no-deps -T --entrypoint system-updater system-updater self-update-helper " + server.config.StatePath + " attempt-123 " + server.config.Workdir + " system-updater sha256:new compose -p core-panel -f docker-compose.yml":
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
		command := strings.Join(args, " ")
		switch command {
		case "compose ps -q system-updater":
			return []byte("new-container"), nil
		case "inspect --format {{.Image}} new-container":
			return []byte("sha256:new"), nil
		case "exec new-container system-updater --healthcheck":
			return nil, nil
		}
		if workdir != "/workspace" || name != "docker" || command != "compose up -d --no-deps --no-build --pull never system-updater" {
			t.Fatalf("unexpected helper command: %s %s in %s", name, strings.Join(args, " "), workdir)
		}
		heartbeat, err := os.ReadFile(selfUpdateHeartbeatPath(statePath))
		if err != nil || strings.TrimSpace(string(heartbeat)) != "attempt-123" {
			t.Fatalf("expected matching heartbeat before Compose starts, got %q (%v)", heartbeat, err)
		}

		return []byte{}, nil
	}

	if err := runSelfUpdateHelper(context.Background(), []string{statePath, "attempt-123", "/workspace", "system-updater", "sha256:new", "compose"}); err != nil {
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

	if err := runSelfUpdateHelper(context.Background(), []string{statePath, "attempt-123", "/workspace", "system-updater", "sha256:new", "compose"}); err == nil {
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

func TestCollectImagesIncludesRunningLocalBuilds(t *testing.T) {
	original := commandOutputFunc
	t.Cleanup(func() { commandOutputFunc = original })
	commandOutputFunc = func(workdir, name string, args ...string) ([]byte, error) {
		command := strings.Join(append([]string{name}, args...), " ")
		switch command {
		case "docker compose -p onehub -f docker-compose.dev.yml config --format json":
			return []byte(`{"services":{"app":{"build":{}},"stopped":{"build":{}}}}`), nil
		case "docker compose -p onehub -f docker-compose.dev.yml ps -q app":
			return []byte("app-container\n"), nil
		case "docker compose -p onehub -f docker-compose.dev.yml ps -q stopped":
			return nil, nil
		case "docker inspect --format {{.Config.Image}} app-container":
			return []byte("onehub-app\n"), nil
		case "docker inspect --format {{.Image}} app-container":
			return []byte("sha256:old\n"), nil
		case "docker image inspect --format {{.Id}} onehub-app":
			return []byte("sha256:new\n"), nil
		default:
			t.Fatalf("unexpected command: %s", command)
			return nil, nil
		}
	}
	server := Server{config: Config{
		ComposeFiles:    []string{"docker-compose.dev.yml"},
		ProjectName:     "onehub",
		RuntimeServices: []string{"app"},
	}}
	images, err := server.collectImages()
	if err != nil {
		t.Fatal(err)
	}
	if len(images) != 1 || images[0].Image != "onehub-app" || images[0].CurrentDigest != "sha256:old" || !images[0].UpdateAvailable {
		t.Fatalf("expected running local image with rebuilt image available, got %#v", images)
	}
}

func TestCurrentImagePropagatesInspectFailure(t *testing.T) {
	original := commandOutputFunc
	t.Cleanup(func() { commandOutputFunc = original })
	commandOutputFunc = func(workdir, name string, args ...string) ([]byte, error) {
		if args[0] == "compose" {
			return []byte("app-container"), nil
		}
		return nil, fmt.Errorf("container disappeared")
	}
	server := Server{}
	if _, err := server.currentImage("app"); err == nil {
		t.Fatal("expected container inspection error")
	}
}

func TestInitializeImagesPopulatesStatusWithoutPulling(t *testing.T) {
	original := commandOutputFunc
	t.Cleanup(func() { commandOutputFunc = original })
	commandOutputFunc = func(workdir, name string, args ...string) ([]byte, error) {
		command := strings.Join(append([]string{name}, args...), " ")
		switch command {
		case "docker compose -p onehub config --format json":
			return []byte(`{"services":{"app":{"image":"app:local"}}}`), nil
		case "docker compose -p onehub ps -q app":
			return []byte("app-container"), nil
		case "docker image inspect --format {{.Id}} app:local":
			return []byte("sha256:current"), nil
		case "docker inspect --format {{.Image}} app-container":
			return []byte("sha256:current"), nil
		default:
			t.Fatalf("unexpected startup command: %s", command)
			return nil, nil
		}
	}
	server := Server{config: Config{ProjectName: "onehub"}, state: State{UpdateAvailable: true}}
	if err := server.initializeImages(); err != nil {
		t.Fatal(err)
	}
	response := httptest.NewRecorder()
	server.status(response, httptest.NewRequest(http.MethodGet, "/status", nil))
	var state State
	if err := json.Unmarshal(response.Body.Bytes(), &state); err != nil {
		t.Fatal(err)
	}
	if len(state.Images) != 1 || state.Images[0].Image != "app:local" || state.UpdateAvailable || state.LastCheckAt != nil {
		t.Fatalf("expected initial inventory without claiming a registry check: %#v", state)
	}
}

func TestDetachedHelperStartKeepsUpdatePending(t *testing.T) {
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
			return []byte(`{"services":{"system-updater":{"image":"updater:staging"}}}`), nil
		case "docker image inspect --format {{.Id}} updater:staging":
			return []byte("sha256:new"), nil
		case "docker compose -p core-panel -f docker-compose.yml ps -q system-updater":
			return nil, nil
		case "docker compose -p core-panel -f docker-compose.yml run --detach --pull never --rm --no-deps -T --entrypoint system-updater system-updater self-update-helper " + server.config.StatePath + " attempt-123 " + server.config.Workdir + " system-updater sha256:new compose -p core-panel -f docker-compose.yml":
			if !server.state.UpdateRunning || server.state.LastUpdateState != "running" || !server.state.SelfUpdatePending {
				t.Fatalf("expected self-update to remain nonterminal while Compose is running, got %#v", server.state)
			}

			if _, exists := server.state.AttemptResults["attempt-123"]; exists {
				t.Fatalf("expected no terminal attempt result before self-update completes")
			}

			return []byte("helper-container"), nil
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

	if !server.state.UpdateRunning || !server.state.SelfUpdatePending || server.state.LastUpdateState != "running" {
		t.Fatalf("detached helper start must not complete the update: %#v", server.state)
	}
	if _, exists := server.state.AttemptResults["attempt-123"]; exists {
		t.Fatal("helper start is not a terminal attempt result")
	}
	if err := writeSelfUpdateCompletion(server.config.StatePath, "attempt-123"); err != nil {
		t.Fatal(err)
	}
	server.reconcilePendingSelfUpdate()
	if server.state.UpdateRunning || server.state.AttemptResults["attempt-123"].LastUpdateState != "success" {
		t.Fatal("verified completion must finish the matching attempt")
	}
}

func TestHealthcheckAuthenticatesAgainstUpdaterStatus(t *testing.T) {
	for _, status := range []int{http.StatusOK, http.StatusUnauthorized, http.StatusServiceUnavailable} {
		api := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			if r.URL.Path != "/status" || r.Header.Get("Authorization") != "Bearer test-token" {
				t.Error("incorrect health probe")
			}
			w.WriteHeader(status)
			_ = json.NewEncoder(w).Encode(map[string]bool{"update_running": true})
		}))
		t.Setenv("UPDATER_ADDR", strings.TrimPrefix(api.URL, "http://"))
		t.Setenv("UPDATER_TOKEN", "test-token")
		err := updaterHealthcheck()
		api.Close()
		if (err == nil) != (status == http.StatusOK) {
			t.Fatalf("unexpected health result for %d: %v", status, err)
		}
	}
}

func TestSelfUpdateReadinessRejectsWrongImageAndUnreachableAPI(t *testing.T) {
	for _, scenario := range []string{"ready", "wrong-image", "unreachable"} {
		t.Run(scenario, func(t *testing.T) {
			original := commandOutputFunc
			t.Cleanup(func() { commandOutputFunc = original })
			commandOutputFunc = func(workdir, name string, args ...string) ([]byte, error) {
				switch strings.Join(args, " ") {
				case "compose -p example ps -q system-updater":
					return []byte("new-container"), nil
				case "inspect --format {{.Image}} new-container":
					if scenario == "wrong-image" {
						return []byte("sha256:old"), nil
					}
					return []byte("sha256:new"), nil
				case "exec new-container system-updater --healthcheck":
					if scenario == "unreachable" {
						return nil, errors.New("connection refused")
					}
					return nil, nil
				}
				t.Fatalf("unexpected command: %v", args)
				return nil, nil
			}
			ctx, cancel := context.WithTimeout(context.Background(), 20*time.Millisecond)
			defer cancel()
			err := waitForSelfUpdate(ctx, "/workspace", []string{"compose", "-p", "example"}, "system-updater", "sha256:new")
			if (err == nil) != (scenario == "ready") {
				t.Fatalf("unexpected readiness result: %v", err)
			}
		})
	}
}

func TestHelperFailureMarkerFinishesOnlyItsOwnAttempt(t *testing.T) {
	now := time.Now().UTC()
	server := Server{config: Config{StatePath: filepath.Join(t.TempDir(), "state.json")}, state: State{UpdateAttemptID: "current", UpdateRunning: true, SelfUpdatePending: true, LastUpdateState: "running", LastUpdateAt: &now}}
	if err := writeSelfUpdateMarker(selfUpdateFailurePath(server.config.StatePath), "previous"); err != nil {
		t.Fatal(err)
	}
	server.reconcilePendingSelfUpdate()
	if !server.state.UpdateRunning {
		t.Fatal("stale helper failure must not finish current attempt")
	}
	if err := writeSelfUpdateMarker(selfUpdateFailurePath(server.config.StatePath), "current"); err != nil {
		t.Fatal(err)
	}
	server.reconcilePendingSelfUpdate()
	if server.state.UpdateRunning || server.state.AttemptResults["current"].LastUpdateState != "failed" {
		t.Fatal("matching helper failure must finish the attempt immediately")
	}
}
