package main

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strings"
	"sync"
	"time"
)

type Config struct {
	Addr                     string
	ComposeEnvFile           string
	ComposeFiles             []string
	ForceSelfUpdateAvailable bool
	ProjectName              string
	RuntimeServices          []string
	SelfUpdate               bool
	SelfService              string
	StatePath                string
	Token                    string
	Workdir                  string
}

type ImageState struct {
	AvailableDigest      string `json:"available_digest,omitempty"`
	CurrentDigest        string `json:"current_digest,omitempty"`
	Image                string `json:"image"`
	ManualUpdateRequired bool   `json:"manual_update_required"`
	Service              string `json:"service"`
	UpdateAvailable      bool   `json:"update_available"`
}

type LogEntry struct {
	Level     string    `json:"level"`
	Message   string    `json:"message"`
	Timestamp time.Time `json:"timestamp"`
}

type State struct {
	Images          []ImageState `json:"images"`
	LastCheckAt     *time.Time   `json:"last_check_at,omitempty"`
	LastUpdateAt    *time.Time   `json:"last_update_at,omitempty"`
	LastUpdateState string       `json:"last_update_state,omitempty"`
	Logs            []LogEntry   `json:"logs"`
	UpdateAvailable bool         `json:"update_available"`
	UpdateRunning   bool         `json:"update_running"`
}

type Server struct {
	config Config
	mu     sync.Mutex
	state  State
}

type composeConfig struct {
	Services map[string]struct {
		Image string `json:"image"`
	} `json:"services"`
}

type composeLabels struct {
	ComposeFiles []string
	ProjectName  string
	Workdir      string
}

type containerInspect struct {
	Config struct {
		Labels map[string]string `json:"Labels"`
	} `json:"Config"`
}

const (
	autoConfigValue           = "auto"
	composeProjectLabel       = "com.docker.compose.project"
	composeProjectConfigFiles = "com.docker.compose.project.config_files"
	composeServiceLabel       = "com.docker.compose.service"
	composeProjectWorkingDir  = "com.docker.compose.project.working_dir"
	defaultComposeFiles       = "docker-compose.prod.yml"
	defaultComposeProjectName = "core-panel"
	defaultComposeWorkdir     = "/workspace"
)

var loadComposeLabelsFunc = loadComposeLabels
var commandOutputFunc = commandOutput
var sleepFunc = time.Sleep

func main() {
	config, err := loadConfig()
	if err != nil {
		log.Fatalf("configuration error: %v", err)
	}

	server := &Server{config: config}
	server.state = server.loadState()

	mux := http.NewServeMux()
	mux.HandleFunc("GET /status", server.withAuth(server.status))
	mux.HandleFunc("POST /check", server.withAuth(server.check))
	mux.HandleFunc("POST /update", server.withAuth(server.update))
	mux.HandleFunc("GET /logs", server.withAuth(server.logs))

	log.Printf("system updater listening on %s", config.Addr)
	if err := http.ListenAndServe(config.Addr, mux); err != nil {
		log.Fatal(err)
	}
}

func loadConfig() (Config, error) {
	token := strings.TrimSpace(os.Getenv("UPDATER_TOKEN"))
	if token == "" {
		return Config{}, errors.New("UPDATER_TOKEN is required")
	}

	workdirConfig := strings.TrimSpace(os.Getenv("UPDATER_COMPOSE_WORKDIR"))
	envFileConfig := env("UPDATER_COMPOSE_ENV_FILE", autoConfigValue)
	filesConfig := strings.TrimSpace(os.Getenv("UPDATER_COMPOSE_FILES"))
	projectNameConfig := strings.TrimSpace(os.Getenv("UPDATER_COMPOSE_PROJECT_NAME"))

	labels := composeLabels{}
	if isAutoConfig(workdirConfig) || isAutoConfig(filesConfig) || isAutoConfig(projectNameConfig) {
		var err error
		labels, err = loadComposeLabelsFunc()
		if err != nil {
			return Config{}, err
		}
	}

	workdir, err := composeSetting("UPDATER_COMPOSE_WORKDIR", workdirConfig, defaultComposeWorkdir, labels.Workdir)
	if err != nil {
		return Config{}, err
	}

	projectName, err := composeSetting("UPDATER_COMPOSE_PROJECT_NAME", projectNameConfig, defaultComposeProjectName, labels.ProjectName)
	if err != nil {
		return Config{}, err
	}

	files, err := composeFilesSetting(filesConfig, labels.ComposeFiles)
	if err != nil {
		return Config{}, err
	}
	if len(files) == 0 {
		return Config{}, errors.New("UPDATER_COMPOSE_FILES must contain at least one compose file")
	}

	envFile, err := composeEnvFileSetting(envFileConfig, workdir)
	if err != nil {
		return Config{}, err
	}

	runtimeServices := splitList(env("UPDATER_RUNTIME_SERVICES", "app,horizon,scheduler,nginx"))
	if len(runtimeServices) == 0 {
		return Config{}, errors.New("UPDATER_RUNTIME_SERVICES must contain at least one service")
	}
	if !validServiceNames(runtimeServices) {
		return Config{}, errors.New("UPDATER_RUNTIME_SERVICES contains invalid service names")
	}

	selfService := env("UPDATER_SELF_SERVICE", "system-updater")
	if !validServiceName(selfService) {
		return Config{}, errors.New("UPDATER_SELF_SERVICE contains invalid characters")
	}
	if containsService(runtimeServices, selfService) {
		return Config{}, errors.New("UPDATER_RUNTIME_SERVICES must not include UPDATER_SELF_SERVICE")
	}

	for index, file := range files {
		if !filepath.IsAbs(file) {
			files[index] = filepath.Join(workdir, file)
		}
	}

	return Config{
		Addr:                     env("UPDATER_ADDR", ":8080"),
		ComposeEnvFile:           envFile,
		ComposeFiles:             files,
		ForceSelfUpdateAvailable: envBool("UPDATER_FORCE_SELF_UPDATE_AVAILABLE", false),
		ProjectName:              projectName,
		RuntimeServices:          runtimeServices,
		SelfUpdate:               envBool("UPDATER_SELF_UPDATE_ENABLED", false),
		SelfService:              selfService,
		StatePath:                env("UPDATER_STATE_PATH", "/data/state.json"),
		Token:                    token,
		Workdir:                  workdir,
	}, nil
}

func composeSetting(name string, configured string, fallback string, labelValue string) (string, error) {
	if isAutoConfig(configured) {
		if strings.TrimSpace(labelValue) == "" {
			return "", fmt.Errorf("%s=auto requires Docker Compose label metadata", name)
		}

		return strings.TrimSpace(labelValue), nil
	}

	if strings.TrimSpace(configured) != "" {
		return strings.TrimSpace(configured), nil
	}

	return fallback, nil
}

func composeFilesSetting(configured string, labelFiles []string) ([]string, error) {
	if isAutoConfig(configured) {
		if len(labelFiles) == 0 {
			return nil, errors.New("UPDATER_COMPOSE_FILES=auto requires Docker Compose config file labels")
		}

		return labelFiles, nil
	}

	if strings.TrimSpace(configured) != "" {
		return splitList(configured), nil
	}

	return splitList(defaultComposeFiles), nil
}

func composeEnvFileSetting(configured string, workdir string) (string, error) {
	if isAutoConfig(configured) {
		candidate := filepath.Join(workdir, "stack.env")
		if _, err := os.Stat(candidate); err == nil {
			return candidate, nil
		} else if errors.Is(err, os.ErrNotExist) {
			return "", nil
		} else {
			return "", err
		}
	}

	if strings.TrimSpace(configured) == "" {
		return "", nil
	}

	if filepath.IsAbs(configured) {
		return configured, nil
	}

	return filepath.Join(workdir, configured), nil
}

func isAutoConfig(value string) bool {
	return strings.EqualFold(strings.TrimSpace(value), autoConfigValue)
}

func loadComposeLabels() (composeLabels, error) {
	containerID, err := ownContainerID()
	if err != nil {
		return composeLabels{}, err
	}

	labels, err := loadComposeLabelsForContainer(containerID)
	if err == nil {
		return labels, nil
	}

	if strings.Contains(err.Error(), "No such container") {
		return loadComposeLabelsForService(env("UPDATER_SELF_SERVICE", "system-updater"))
	}

	return composeLabels{}, err
}

func loadComposeLabelsForContainer(containerID string) (composeLabels, error) {
	var output []byte
	var err error
	for attempt := 1; attempt <= 10; attempt++ {
		output, err = commandOutputFunc("", "docker", "container", "inspect", containerID)
		if err == nil {
			break
		}

		if attempt < 10 && strings.Contains(err.Error(), "No such container") {
			sleepFunc(time.Second)
			continue
		}

		return composeLabels{}, err
	}

	var inspected []containerInspect
	if err := json.Unmarshal(output, &inspected); err != nil {
		return composeLabels{}, err
	}
	if len(inspected) == 0 {
		return composeLabels{}, errors.New("docker container inspect returned no container metadata")
	}

	labels := inspected[0].Config.Labels
	if labels == nil {
		labels = map[string]string{}
	}

	return composeLabels{
		ComposeFiles: splitList(labels[composeProjectConfigFiles]),
		ProjectName:  strings.TrimSpace(labels[composeProjectLabel]),
		Workdir:      strings.TrimSpace(labels[composeProjectWorkingDir]),
	}, nil
}

func loadComposeLabelsForService(service string) (composeLabels, error) {
	output, err := commandOutputFunc("", "docker", "container", "ls", "-q", "--filter", "label="+composeServiceLabel+"="+service)
	if err != nil {
		return composeLabels{}, err
	}

	containerIDs := splitList(string(output))
	if len(containerIDs) == 0 {
		return composeLabels{}, fmt.Errorf("could not find running container for compose service %q", service)
	}

	var lastErr error
	for _, containerID := range containerIDs {
		labels, err := loadComposeLabelsForContainer(containerID)
		if err == nil && labels.ProjectName != "" && labels.Workdir != "" {
			return labels, nil
		}
		if err != nil {
			lastErr = err
		}
	}

	if lastErr != nil {
		return composeLabels{}, lastErr
	}

	return composeLabels{}, fmt.Errorf("could not resolve compose labels for service %q", service)
}

func ownContainerID() (string, error) {
	content, err := os.ReadFile("/etc/hostname")
	if err != nil {
		return "", err
	}

	containerID := strings.TrimSpace(string(content))
	if containerID == "" {
		return "", errors.New("container hostname is empty")
	}

	return containerID, nil
}

func (server *Server) withAuth(next http.HandlerFunc) http.HandlerFunc {
	return func(response http.ResponseWriter, request *http.Request) {
		if request.Header.Get("Authorization") != "Bearer "+server.config.Token {
			writeJSON(response, http.StatusUnauthorized, map[string]string{"message": "unauthorized"})
			return
		}

		next(response, request)
	}
}

func (server *Server) status(response http.ResponseWriter, request *http.Request) {
	server.mu.Lock()
	defer server.mu.Unlock()

	images, err := server.collectImages()
	if err == nil {
		server.state.Images = images
		server.state.UpdateAvailable = anyUpdateAvailable(images)
		server.saveState()
	} else {
		server.addLog("error", fmt.Sprintf("status failed: %s", err.Error()))
	}

	writeJSON(response, http.StatusOK, server.state)
}

func (server *Server) check(response http.ResponseWriter, request *http.Request) {
	server.mu.Lock()
	defer server.mu.Unlock()

	if server.state.UpdateRunning {
		writeJSON(response, http.StatusConflict, map[string]string{"message": "update already running"})
		return
	}

	server.addLog("info", "checking for image updates")
	if err := server.compose("pull"); err != nil {
		errorMessage := err.Error()

		server.addLog("error", fmt.Sprintf("check failed: %s", errorMessage))
		writeJSON(response, http.StatusInternalServerError, map[string]string{
			"error":   errorMessage,
			"message": "check failed",
		})
		return
	}

	now := time.Now().UTC()
	images, err := server.collectImages()
	if err != nil {
		errorMessage := err.Error()

		server.addLog("error", fmt.Sprintf("status after check failed: %s", errorMessage))
		writeJSON(response, http.StatusInternalServerError, map[string]string{
			"error":   errorMessage,
			"message": "status failed",
		})
		return
	}

	server.state.Images = images
	server.state.LastCheckAt = &now
	server.state.UpdateAvailable = anyUpdateAvailable(images)
	server.addLog("info", "image update check completed")
	server.saveState()

	writeJSON(response, http.StatusOK, server.state)
}

func (server *Server) update(response http.ResponseWriter, request *http.Request) {
	server.mu.Lock()
	if server.state.UpdateRunning {
		server.mu.Unlock()
		writeJSON(response, http.StatusConflict, map[string]string{"message": "update already running"})
		return
	}

	now := time.Now().UTC()
	server.state.UpdateRunning = true
	server.state.LastUpdateAt = &now
	server.state.LastUpdateState = "running"
	server.addLog("info", "system update started")
	server.saveState()
	server.mu.Unlock()

	go server.runUpdate()

	server.mu.Lock()
	defer server.mu.Unlock()
	writeJSON(response, http.StatusAccepted, server.state)
}

func (server *Server) runUpdate() {
	err := server.compose("pull")
	if err == nil {
		err = server.compose(server.runtimeUpdateArgs()...)
	}

	server.mu.Lock()

	now := time.Now().UTC()
	server.state.LastUpdateAt = &now
	server.state.UpdateRunning = false

	if err != nil {
		server.state.LastUpdateState = "failed"
		server.addLog("error", fmt.Sprintf("system update failed: %s", err.Error()))
		server.saveState()
		server.mu.Unlock()
		return
	}

	images, statusErr := server.collectImages()
	if statusErr == nil {
		server.state.Images = images
		server.state.UpdateAvailable = anyUpdateAvailable(images)
	}

	server.state.LastUpdateState = "success"
	server.addLog("info", "system update completed")
	server.saveState()
	server.mu.Unlock()

	if server.config.SelfUpdate {
		if err := server.updateSelfService(); err != nil {
			server.mu.Lock()
			server.state.LastUpdateState = "failed"
			server.addLog("error", fmt.Sprintf("updater service update failed: %s", err.Error()))
			server.saveState()
			server.mu.Unlock()
		}

		return
	}

	server.mu.Lock()
	server.addLog("info", fmt.Sprintf("updater service self-update skipped for %q", server.config.SelfService))
	server.saveState()
	server.mu.Unlock()
}

func (server *Server) runtimeUpdateArgs() []string {
	return append([]string{"up", "-d", "--no-deps"}, server.config.RuntimeServices...)
}

func (server *Server) updateSelfService() error {
	server.mu.Lock()
	server.addLog("info", fmt.Sprintf("updating updater service %q last", server.config.SelfService))
	server.saveState()
	server.mu.Unlock()

	if err := server.compose("up", "-d", "--no-deps", server.config.SelfService); err != nil {
		return err
	}

	return nil
}

func (server *Server) logs(response http.ResponseWriter, request *http.Request) {
	server.mu.Lock()
	defer server.mu.Unlock()

	writeJSON(response, http.StatusOK, map[string][]LogEntry{"entries": server.state.Logs})
}

func (server *Server) collectImages() ([]ImageState, error) {
	services, err := server.composeConfig()
	if err != nil {
		return nil, err
	}

	images := make([]ImageState, 0, len(services.Services))
	for service, definition := range services.Services {
		if definition.Image == "" {
			continue
		}

		current, _ := server.currentDigest(service)
		available, _ := server.imageDigest(definition.Image)

		images = append(images, server.imageState(service, definition.Image, current, available))
	}

	if server.config.ForceSelfUpdateAvailable && !containsImageService(images, server.config.SelfService) {
		images = append(images, server.imageState(server.config.SelfService, server.config.SelfService, "", ""))
	}

	return images, nil
}

func (server *Server) imageState(service string, image string, current string, available string) ImageState {
	updateAvailable := current != "" && available != "" && current != available

	if server.config.ForceSelfUpdateAvailable && service == server.config.SelfService {
		if current == "" {
			current = "forced-current-digest"
		}

		if available == "" || available == current {
			available = current + "-forced"
		}

		updateAvailable = true
	}

	return ImageState{
		AvailableDigest:      available,
		CurrentDigest:        current,
		Image:                image,
		ManualUpdateRequired: updateAvailable && !server.autoUpdateEligible(service),
		Service:              service,
		UpdateAvailable:      updateAvailable,
	}
}

func (server *Server) autoUpdateEligible(service string) bool {
	if containsService(server.config.RuntimeServices, service) {
		return true
	}

	return server.config.SelfUpdate && service == server.config.SelfService
}

func (server *Server) composeConfig() (composeConfig, error) {
	output, err := server.composeOutput("config", "--format", "json")
	if err != nil {
		return composeConfig{}, err
	}

	var config composeConfig
	if err := json.Unmarshal(output, &config); err != nil {
		return composeConfig{}, err
	}

	return config, nil
}

func (server *Server) currentDigest(service string) (string, error) {
	output, err := server.composeOutput("ps", "-q", service)
	if err != nil {
		return "", err
	}

	containerID := strings.TrimSpace(string(output))
	if containerID == "" {
		return "", nil
	}

	imageID, err := commandOutputFunc(server.config.Workdir, "docker", "inspect", "--format", "{{.Image}}", containerID)
	if err != nil {
		return "", err
	}

	return strings.TrimSpace(string(imageID)), nil
}

func (server *Server) imageDigest(image string) (string, error) {
	output, err := commandOutputFunc(server.config.Workdir, "docker", "image", "inspect", "--format", "{{.Id}}", image)
	if err != nil {
		return "", err
	}

	return strings.TrimSpace(string(output)), nil
}

func (server *Server) compose(args ...string) error {
	_, err := server.composeOutput(args...)
	return err
}

func (server *Server) composeOutput(args ...string) ([]byte, error) {
	commandArgs := []string{"compose"}
	if server.config.ComposeEnvFile != "" {
		commandArgs = append(commandArgs, "--env-file", server.config.ComposeEnvFile)
	}
	commandArgs = append(commandArgs, "-p", server.config.ProjectName)
	for _, file := range server.config.ComposeFiles {
		commandArgs = append(commandArgs, "-f", file)
	}
	commandArgs = append(commandArgs, args...)

	return commandOutputFunc(server.config.Workdir, "docker", commandArgs...)
}

func commandOutput(workdir string, name string, args ...string) ([]byte, error) {
	command := exec.Command(name, args...)
	command.Dir = workdir

	var stderr bytes.Buffer
	command.Stderr = &stderr

	output, err := command.Output()
	if err != nil {
		return nil, fmt.Errorf("%s %s: %w: %s", name, strings.Join(args, " "), err, strings.TrimSpace(stderr.String()))
	}

	return output, nil
}

func (server *Server) addLog(level string, message string) {
	server.state.Logs = append([]LogEntry{{
		Level:     level,
		Message:   message,
		Timestamp: time.Now().UTC(),
	}}, server.state.Logs...)

	if len(server.state.Logs) > 300 {
		server.state.Logs = server.state.Logs[:300]
	}
}

func (server *Server) loadState() State {
	content, err := os.ReadFile(server.config.StatePath)
	if err != nil {
		return State{Logs: []LogEntry{}}
	}

	var state State
	if err := json.Unmarshal(content, &state); err != nil {
		return State{Logs: []LogEntry{}}
	}

	if state.UpdateRunning {
		server.state = state
		server.state.UpdateRunning = false
		server.state.LastUpdateState = "failed"
		server.addLog("error", "stale running update state found on startup; marking update as failed")
		server.saveState()

		return server.state
	}

	return state
}

func (server *Server) saveState() {
	if err := os.MkdirAll(filepath.Dir(server.config.StatePath), 0o750); err != nil {
		log.Printf("failed to create state directory: %v", err)
		return
	}

	content, err := json.MarshalIndent(server.state, "", "  ")
	if err != nil {
		log.Printf("failed to encode state: %v", err)
		return
	}

	if err := os.WriteFile(server.config.StatePath, content, 0o640); err != nil {
		log.Printf("failed to write state: %v", err)
	}
}

func anyUpdateAvailable(images []ImageState) bool {
	for _, image := range images {
		if image.UpdateAvailable && !image.ManualUpdateRequired {
			return true
		}
	}

	return false
}

func containsImageService(images []ImageState, target string) bool {
	for _, image := range images {
		if image.Service == target {
			return true
		}
	}

	return false
}

func writeJSON(response http.ResponseWriter, status int, payload any) {
	response.Header().Set("Content-Type", "application/json")
	response.WriteHeader(status)
	_ = json.NewEncoder(response).Encode(payload)
}

func env(key string, fallback string) string {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}

	return value
}

func envBool(key string, fallback bool) bool {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}

	return strings.EqualFold(value, "true") || value == "1" || strings.EqualFold(value, "yes")
}

func validServiceName(service string) bool {
	return regexp.MustCompile(`^[A-Za-z0-9][A-Za-z0-9_.-]*$`).MatchString(service)
}

func validServiceNames(services []string) bool {
	for _, service := range services {
		if !validServiceName(service) {
			return false
		}
	}

	return true
}

func containsService(services []string, target string) bool {
	for _, service := range services {
		if service == target {
			return true
		}
	}

	return false
}

func splitList(value string) []string {
	parts := strings.Split(value, ",")
	result := make([]string, 0, len(parts))

	for _, part := range parts {
		trimmed := strings.TrimSpace(part)
		if trimmed != "" {
			result = append(result, trimmed)
		}
	}

	return result
}
