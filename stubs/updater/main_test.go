package main

import (
	"errors"
	"path/filepath"
	"strings"
	"testing"
)

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

func TestRunUpdateMarksStateFailedWhenSelfUpdateFails(t *testing.T) {
	originalCommandOutputFunc := commandOutputFunc
	defer func() {
		commandOutputFunc = originalCommandOutputFunc
	}()

	commandOutputFunc = func(workdir string, name string, args ...string) ([]byte, error) {
		command := strings.Join(append([]string{name}, args...), " ")

		switch command {
		case "docker compose -p core-panel -f docker-compose.yml pull":
			return []byte{}, nil
		case "docker compose -p core-panel -f docker-compose.yml up -d --no-deps app":
			return []byte{}, nil
		case "docker compose -p core-panel -f docker-compose.yml config --format json":
			return []byte(`{"services":{}}`), nil
		case "docker compose -p core-panel -f docker-compose.yml up -d --no-deps system-updater":
			return nil, errors.New("self-update failed")
		default:
			t.Fatalf("unexpected command: %s", command)
			return nil, nil
		}
	}

	server := &Server{
		config: Config{
			ComposeFiles:    []string{"docker-compose.yml"},
			ProjectName:     "core-panel",
			RuntimeServices: []string{"app"},
			SelfService:     "system-updater",
			SelfUpdate:      true,
			StatePath:       filepath.Join(t.TempDir(), "state.json"),
		},
		state: State{
			Logs: []LogEntry{},
		},
	}

	server.runUpdate()

	if server.state.LastUpdateState != "failed" {
		t.Fatalf("expected self-update failure to mark last update state as failed, got %q", server.state.LastUpdateState)
	}

	if server.state.UpdateRunning {
		t.Fatalf("expected update running flag to be cleared")
	}

	if len(server.state.Logs) == 0 || !strings.Contains(server.state.Logs[0].Message, "updater service update failed") {
		t.Fatalf("expected self-update failure to be logged")
	}
}
