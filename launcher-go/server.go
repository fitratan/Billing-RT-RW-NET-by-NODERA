package main

import (
	"fmt"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"time"
	"unsafe"
)

var (
	shell32DLL           = syscall.NewLazyDLL("shell32.dll")
	procShellExecuteW    = shell32DLL.NewProc("ShellExecuteW")
	procShellNotifyIconW = shell32DLL.NewProc("Shell_NotifyIconW")
)

type ServerManager struct {
	PhpBinary string
	BaseDir   string
	Port      int
	Process   *exec.Cmd
	IsRunning bool
	LocalIP   string
}

// OpenBrowser opens the URL in the user's default browser
func OpenBrowser(url string) error {
	verb, _ := syscall.UTF16PtrFromString("open")
	file, _ := syscall.UTF16PtrFromString(url)
	procShellExecuteW.Call(0, uintptr(unsafePointer(verb)), uintptr(unsafePointer(file)), 0, 0, 1)
	return nil
}

// OpenAppWindow opens the application in a dedicated standalone desktop app window (zero address bar/tabs)
func OpenAppWindow(url string) (*exec.Cmd, error) {
	if isRunningUnderWine() {
		linuxBrowsers := []string{
			"/usr/bin/google-chrome",
			"/usr/bin/google-chrome-stable",
			"/usr/bin/chromium-browser",
			"/usr/bin/chromium",
			"/usr/bin/brave-browser",
			"/usr/bin/microsoft-edge-stable",
			"/usr/bin/microsoft-edge",
		}
		for _, b := range linuxBrowsers {
			cmd := exec.Command("cmd", "/c", "start", "/unix", b, "--app="+url, "--window-size=1280,840")
			if err := cmd.Start(); err == nil {
				return cmd, nil
			}
		}
		cmd := exec.Command("cmd", "/c", "start", "/unix", "/usr/bin/xdg-open", url)
		return cmd, cmd.Start()
	}

	winCandidates := []string{
		`C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe`,
		`C:\Program Files\Microsoft\Edge\Application\msedge.exe`,
		`C:\Program Files\Google\Chrome\Application\chrome.exe`,
		`C:\Program Files (x86)\Google\Chrome\Application\chrome.exe`,
	}
	for _, c := range winCandidates {
		if fi, err := os.Stat(c); err == nil && !fi.IsDir() {
			cmd := exec.Command(c, "--app="+url, "--window-size=1280,840")
			if err := cmd.Start(); err == nil {
				return cmd, nil
			}
		}
	}

	return nil, OpenBrowser(url)
}

func unsafePointer(p *uint16) uintptr {
	return uintptr(unsafe.Pointer(p))
}

// FindPhpBinary locates portable or system PHP executable
func FindPhpBinary(baseDir string) string {
	candidates := []string{
		filepath.Join(baseDir, "bin", "php-win", "php.exe"),
		filepath.Join(baseDir, "php", "php.exe"),
		filepath.Join(baseDir, "bin", "php", "php.exe"),
		filepath.Join(baseDir, "..", "mikhmon-desktop-nodera", "bin", "php-win", "php.exe"),
		filepath.Join(baseDir, "..", "bin", "php-win", "php.exe"),
		`C:\xampp\php\php.exe`,
		`C:\laragon\bin\php\php.exe`,
	}

	// Also search Laragon dynamic directories
	laragonDir := `C:\laragon\bin\php`
	if entries, err := os.ReadDir(laragonDir); err == nil {
		for _, e := range entries {
			if e.IsDir() && strings.HasPrefix(e.Name(), "php-") {
				p := filepath.Join(laragonDir, e.Name(), "php.exe")
				candidates = append(candidates, p)
			}
		}
	}

	for _, c := range candidates {
		if fi, err := os.Stat(c); err == nil && !fi.IsDir() {
			return c
		}
	}

	if sysPhp, err := exec.LookPath("php.exe"); err == nil {
		return sysPhp
	}
	if sysPhp, err := exec.LookPath("php"); err == nil {
		return sysPhp
	}

	return ""
}

// GetLocalLANIP retrieves primary LAN IP address
func GetLocalLANIP() string {
	addrs, err := net.InterfaceAddrs()
	if err != nil {
		return "127.0.0.1"
	}
	for _, a := range addrs {
		if ipNet, ok := a.(*net.IPNet); ok && !ipNet.IP.IsLoopback() {
			if ipNet.IP.To4() != nil {
				ipStr := ipNet.IP.String()
				if strings.HasPrefix(ipStr, "192.168.") || strings.HasPrefix(ipStr, "10.") || strings.HasPrefix(ipStr, "172.") {
					return ipStr
				}
			}
		}
	}
	return "127.0.0.1"
}

// ConfigurePhpIni ensures php.ini has absolute paths for extensions and TLS CA bundle
func ConfigurePhpIni(phpBin, baseDir string) {
	phpDir := filepath.Dir(phpBin)
	iniPath := filepath.Join(phpDir, "php.ini")
	extDir := filepath.Join(phpDir, "ext")

	caCandidates := []string{
		filepath.Join(phpDir, "cacert.pem"),
		filepath.Join(baseDir, "resources", "certs", "cacert.pem"),
		filepath.Join(baseDir, "cacert.pem"),
	}

	var caPath string
	for _, c := range caCandidates {
		if _, err := os.Stat(c); err == nil {
			caPath = c
			break
		}
	}

	data, err := os.ReadFile(iniPath)
	if err != nil {
		return
	}
	content := string(data)

	extDirClean := filepath.Clean(extDir)
	caPathClean := filepath.Clean(caPath)

	lines := strings.Split(content, "\n")
	var newLines []string
	extDirFound := false

	for _, line := range lines {
		trimmed := strings.TrimSpace(line)
		if strings.HasPrefix(trimmed, "extension_dir") {
			newLines = append(newLines, fmt.Sprintf("extension_dir = \"%s\"", extDirClean))
			extDirFound = true
		} else if strings.HasPrefix(trimmed, "curl.cainfo") && caPathClean != "" {
			newLines = append(newLines, fmt.Sprintf("curl.cainfo = \"%s\"", caPathClean))
		} else if strings.HasPrefix(trimmed, "openssl.cafile") && caPathClean != "" {
			newLines = append(newLines, fmt.Sprintf("openssl.cafile = \"%s\"", caPathClean))
		} else {
			newLines = append(newLines, line)
		}
	}
	if !extDirFound {
		newLines = append(newLines, fmt.Sprintf("extension_dir = \"%s\"", extDirClean))
	}

	_ = os.WriteFile(iniPath, []byte(strings.Join(newLines, "\n")), 0644)
}

// FindAvailablePort tests if defaultPort is free or finds the next open port
func FindAvailablePort(defaultPort int) int {
	for p := defaultPort; p < defaultPort+30; p++ {
		ln, err := net.Listen("tcp", fmt.Sprintf("0.0.0.0:%d", p))
		if err == nil {
			ln.Close()
			return p
		}
	}
	return defaultPort
}

// ReadConfiguredPort reads HTTP_PORT from .env if present
func ReadConfiguredPort(baseDir string, fallback int) int {
	envPath := filepath.Join(baseDir, ".env")
	data, err := os.ReadFile(envPath)
	if err != nil {
		return fallback
	}
	lines := strings.Split(string(data), "\n")
	for _, line := range lines {
		line = strings.TrimSpace(line)
		if strings.HasPrefix(line, "HTTP_PORT=") {
			val := strings.Trim(strings.TrimPrefix(line, "HTTP_PORT="), `"' `)
			if p, err := strconv.Atoi(val); err == nil && p > 0 {
				return p
			}
		}
	}
	return fallback
}

// NewServerManager initializes manager
func NewServerManager(baseDir string, defaultPort int) *ServerManager {
	phpBin := FindPhpBinary(baseDir)
	if phpBin != "" {
		ConfigurePhpIni(phpBin, baseDir)
	}

	targetPort := ReadConfiguredPort(baseDir, defaultPort)

	return &ServerManager{
		PhpBinary: phpBin,
		BaseDir:   baseDir,
		Port:      FindAvailablePort(targetPort),
		LocalIP:   GetLocalLANIP(),
	}
}

// BootstrapLaravel ensures .env, SQLite DB, and essential migrations are set up
func (sm *ServerManager) BootstrapLaravel() error {
	envPath := filepath.Join(sm.BaseDir, ".env")
	envExamplePath := filepath.Join(sm.BaseDir, ".env.example")
	dbDir := filepath.Join(sm.BaseDir, "database")
	sqlitePath := filepath.Join(dbDir, "database.sqlite")

	// 1. Ensure storage and bootstrap directories exist
	dirs := []string{
		filepath.Join(sm.BaseDir, "storage", "app", "public"),
		filepath.Join(sm.BaseDir, "storage", "framework", "cache", "data"),
		filepath.Join(sm.BaseDir, "storage", "framework", "sessions"),
		filepath.Join(sm.BaseDir, "storage", "framework", "views"),
		filepath.Join(sm.BaseDir, "storage", "logs"),
		filepath.Join(sm.BaseDir, "bootstrap", "cache"),
		dbDir,
	}
	for _, d := range dirs {
		_ = os.MkdirAll(d, 0777)
	}

	isFirstRun := false

	// 2. Setup .env from .env.example if missing
	if _, err := os.Stat(envPath); os.IsNotExist(err) {
		isFirstRun = true
		if data, err := os.ReadFile(envExamplePath); err == nil {
			content := string(data)
			// Configure default standalone mode & SQLite
			content = strings.Replace(content, "DB_CONNECTION=mysql", "DB_CONNECTION=sqlite", 1)
			content = strings.Replace(content, "DB_HOST=127.0.0.1", "# DB_HOST=127.0.0.1", 1)
			content = strings.Replace(content, "DB_PORT=3306", "# DB_PORT=3306", 1)
			content = strings.Replace(content, "DB_DATABASE=nodera_billing", "DB_DATABASE=database/database.sqlite", 1)
			_ = os.WriteFile(envPath, []byte(content), 0644)
		}
	}

	// 3. Ensure database.sqlite exists
	if fi, err := os.Stat(sqlitePath); os.IsNotExist(err) || fi.Size() == 0 {
		isFirstRun = true
		f, err := os.Create(sqlitePath)
		if err == nil {
			f.Close()
		}
	}

	// 4. Run Laravel Initial Artisan Commands if needed
	if isFirstRun && sm.PhpBinary != "" {
		artisanPath := filepath.Join(sm.BaseDir, "artisan")
		phpDir := filepath.Dir(sm.PhpBinary)
		iniPath := filepath.Join(phpDir, "php.ini")

		runArtisan := func(args ...string) {
			cmdArgs := []string{}
			if _, err := os.Stat(iniPath); err == nil {
				cmdArgs = append(cmdArgs, "-c", iniPath)
			}
			cmdArgs = append(cmdArgs, artisanPath)
			cmdArgs = append(cmdArgs, args...)

			cmd := exec.Command(sm.PhpBinary, cmdArgs...)
			cmd.Dir = sm.BaseDir
			cmd.SysProcAttr = &syscall.SysProcAttr{CreationFlags: 0x08000000} // CREATE_NO_WINDOW
			_ = cmd.Run()
		}

		runArtisan("key:generate", "--force")
		runArtisan("migrate", "--force", "--seed")
		runArtisan("storage:link")
		runArtisan("config:clear")
	}

	return nil
}

// DownloadPortablePhp downloads and configures PHP 8.2 portable using PowerShell
func DownloadPortablePhp(baseDir string) error {
	lines := []string{
		"$ProgressPreference = 'SilentlyContinue';",
		"[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12;",
		fmt.Sprintf("$zip = Join-Path '%s' 'php_tmp.zip';", baseDir),
		fmt.Sprintf("$target = Join-Path '%s' 'php';", baseDir),
		"Invoke-WebRequest -Uri 'https://windows.php.net/downloads/releases/archives/php-8.2.14-Win32-vs16-x64.zip' -OutFile $zip;",
		"Expand-Archive -Path $zip -DestinationPath $target -Force;",
		"Remove-Item $zip -Force;",
		"$iniDev = Join-Path $target 'php.ini-development';",
		"$ini = Join-Path $target 'php.ini';",
		"if (Test-Path $iniDev) { Copy-Item $iniDev $ini -Force };",
		`Add-Content $ini "extension_dir = ""` + "ext" + `""";`,
		`Add-Content $ini "extension=curl";`,
		`Add-Content $ini "extension=fileinfo";`,
		`Add-Content $ini "extension=mbstring";`,
		`Add-Content $ini "extension=openssl";`,
		`Add-Content $ini "extension=pdo_sqlite";`,
		`Add-Content $ini "extension=sqlite3";`,
		`Add-Content $ini "extension=sockets";`,
		`Add-Content $ini "extension=gd";`,
	}
	psScript := strings.Join(lines, "\n")

	cmd := exec.Command("powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", psScript)
	cmd.Dir = baseDir
	cmd.SysProcAttr = &syscall.SysProcAttr{CreationFlags: 0x08000000}
	return cmd.Run()
}

// Start launches php artisan serve in the background and verifies socket ready
func (sm *ServerManager) Start() error {
	if sm.IsRunning && sm.Process != nil {
		return nil
	}

	if sm.PhpBinary == "" {
		// Re-scan in case PHP was added
		sm.PhpBinary = FindPhpBinary(sm.BaseDir)
		if sm.PhpBinary == "" {
			res, _, _ := procMessageBoxW.Call(
				appHwnd,
				uintptr(unsafe.Pointer(utf16Ptr("PHP tidak ditemukan di sistem atau folder aplikasi.\n\nApakah Anda ingin mengunduh PHP Portable secara otomatis sekarang?"))),
				uintptr(unsafe.Pointer(utf16Ptr("Unduh PHP Portable Otomatis"))),
				0x00000004|0x00000020, // MB_YESNO | MB_ICONQUESTION
			)
			if res == 6 { // IDYES
				if lblStatus != 0 {
					procSetWindowTextW.Call(lblStatus, uintptr(unsafe.Pointer(utf16Ptr("● MENGUNDUH PHP PORTABLE (Mohon tunggu sebentar)..."))))
				}
				if err := DownloadPortablePhp(sm.BaseDir); err != nil {
					return fmt.Errorf("gagal mengunduh PHP otomatis: %w", err)
				}
				sm.PhpBinary = FindPhpBinary(sm.BaseDir)
			}
			if sm.PhpBinary == "" {
				return fmt.Errorf("PHP executable tidak ditemukan di folder bin\\php-win, php, atau sistem Windows.\nSilakan letakkan folder PHP Portable di dalam folder aplikasi.")
			}
		}
	}

	// Ensure Laravel dependencies & SQLite are initialized
	_ = sm.BootstrapLaravel()

	// Double-check port availability
	sm.Port = FindAvailablePort(sm.Port)

	phpDir := filepath.Dir(sm.PhpBinary)
	iniPath := filepath.Join(phpDir, "php.ini")
	artisanPath := filepath.Join(sm.BaseDir, "artisan")

	// Ensure php.ini is up to date
	ConfigurePhpIni(sm.PhpBinary, sm.BaseDir)

	args := []string{}
	if _, err := os.Stat(iniPath); err == nil {
		args = append(args, "-c", iniPath)
	}

	args = append(args,
		artisanPath,
		"serve",
		"--host=0.0.0.0",
		fmt.Sprintf("--port=%d", sm.Port),
		"--no-reload",
	)

	cmd := exec.Command(sm.PhpBinary, args...)
	cmd.Dir = sm.BaseDir

	// Redirect stdout & stderr to storage/logs/server.log
	logPath := filepath.Join(sm.BaseDir, "storage", "logs", "server.log")
	if logFile, err := os.OpenFile(logPath, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666); err == nil {
		cmd.Stdout = logFile
		cmd.Stderr = logFile
	}

	// Set execution environment
	cmd.Env = append(os.Environ(),
		fmt.Sprintf("PHPRC=%s", phpDir),
		fmt.Sprintf("PATH=%s;%s", phpDir, os.Getenv("PATH")),
	)

	// CREATE_NO_WINDOW = 0x08000000, CREATE_NEW_PROCESS_GROUP = 0x00000200
	cmd.SysProcAttr = &syscall.SysProcAttr{
		CreationFlags: 0x08000000 | 0x00000200,
	}

	if err := cmd.Start(); err != nil {
		return fmt.Errorf("gagal memulai server Laravel Billing: %w", err)
	}

	sm.Process = cmd
	sm.IsRunning = true

	// Verify server socket is actually listening
	var lastDialErr error
	for i := 0; i < 30; i++ {
		time.Sleep(150 * time.Millisecond)
		conn, err := net.DialTimeout("tcp", fmt.Sprintf("127.0.0.1:%d", sm.Port), 250*time.Millisecond)
		if err == nil {
			conn.Close()
			return nil
		}
		lastDialErr = err

		if cmd.Process != nil && cmd.ProcessState != nil && cmd.ProcessState.Exited() {
			sm.IsRunning = false
			return fmt.Errorf("server Laravel berhenti seketika (exit code %d). Cek log di storage/logs/server.log", cmd.ProcessState.ExitCode())
		}
	}

	// It may still be booting slowly on slower HDDs, but socket didn't respond in 4.5s
	return fmt.Errorf("server Laravel belum merespon di port %d: %v", sm.Port, lastDialErr)
}

// Stop cleanly terminates the process tree
func (sm *ServerManager) Stop() {
	if sm.Process != nil && sm.Process.Process != nil {
		pid := sm.Process.Process.Pid
		// Kill process tree in Windows
		killCmd := exec.Command("taskkill", "/F", "/T", "/PID", strconv.Itoa(pid))
		killCmd.SysProcAttr = &syscall.SysProcAttr{CreationFlags: 0x08000000}
		_ = killCmd.Run()

		_ = sm.Process.Process.Kill()
		sm.Process = nil
	}

	// Additional safeguard: ensure no orphaned php processes lingering on sm.Port
	killPortProcess(sm.Port)

	sm.IsRunning = false
}

// killPortProcess finds and kills any orphaned process listening on the specified port
func killPortProcess(port int) {
	cmdStr := fmt.Sprintf("for /f \"tokens=5\" %%%%a in ('netstat -aon ^| findstr :%d') do taskkill /F /PID %%%%a >nul 2>&1", port)
	cmd := exec.Command("cmd", "/c", cmdStr)
	cmd.SysProcAttr = &syscall.SysProcAttr{CreationFlags: 0x08000000}
	_ = cmd.Run()
}
