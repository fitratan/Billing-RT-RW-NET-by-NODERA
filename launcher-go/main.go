package main

import (
	"flag"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"syscall"
	"time"
)

var (
	procCreateMutexW = kernel32DLL.NewProc("CreateMutexW")
	procFindWindowW  = user32DLL.NewProc("FindWindowW")
	procCloseHandle  = kernel32DLL.NewProc("CloseHandle")
)

const (
	AppMutexName = "NODERA_BILLING_DESKTOP_SINGLETON_MUTEX_2026"
)

func main() {
	serverOnly := flag.Bool("server-only", false, "Hanya jalankan panel server tanpa membuka browser otomatis")
	appMode := flag.Bool("app", false, "Buka langsung dalam mode jendela aplikasi desktop mandiri")
	flag.Parse()

	// 1. Single Instance Guard
	mName := utf16Ptr(AppMutexName)
	hMutex, _, _ := procCreateMutexW.Call(0, 1, uintptr(unsafePointer(mName)))
	lastErr, _, _ := kernel32DLL.NewProc("GetLastError").Call()
	if lastErr == 183 { // ERROR_ALREADY_EXISTS
		// Restore existing window if found
		cName := utf16Ptr("NoderaBillingControlWindow2026")
		existingHwnd, _, _ := procFindWindowW.Call(uintptr(unsafePointer(cName)), 0)
		if existingHwnd != 0 {
			procShowWindow.Call(existingHwnd, SW_RESTORE)
			procSetForegroundWindow.Call(existingHwnd)
		}
		return
	}
	defer procCloseHandle.Call(hMutex)

	// 2. Resolve Base Directory
	exePath, err := os.Executable()
	if err != nil {
		exePath = "."
	}
	baseDir := filepath.Dir(exePath)

	// 3. Initialize Server Manager
	sm := NewServerManager(baseDir, 8080)

	// 4. Mode Selection
	exeName := filepath.Base(exePath)

	// If launched as Billing.exe or -app flag, auto-open the app window or browser on launch
	if !*serverOnly && !strings.EqualFold(exeName, "BillingServer.exe") && !strings.EqualFold(exeName, "webserver.exe") {
		go func() {
			// Wait for server to be ready
			for i := 0; i < 40; i++ {
				if sm.IsRunning {
					break
				}
				time.Sleep(200 * time.Millisecond)
			}
			url := fmt.Sprintf("http://127.0.0.1:%d/login", sm.Port)
			if *appMode {
				_, _ = OpenAppWindow(url)
			} else {
				_ = OpenBrowser(url)
			}
		}()
	}

	// Run Native Win32 Control Panel Window with Tray Icon
	CreateServerControlWindow(baseDir, sm)
}

func isRunningUnderWine() bool {
	ntdll := syscall.NewLazyDLL("ntdll.dll")
	return ntdll.NewProc("wine_get_version").Find() == nil
}
