package main

import (
	"fmt"
	"runtime"
	"syscall"
	"time"
	"unsafe"
)

var (
	user32DLL   = syscall.NewLazyDLL("user32.dll")
	kernel32DLL = syscall.NewLazyDLL("kernel32.dll")
	gdi32DLL    = syscall.NewLazyDLL("gdi32.dll")

	procRegisterClassExW   = user32DLL.NewProc("RegisterClassExW")
	procCreateWindowExW     = user32DLL.NewProc("CreateWindowExW")
	procDefWindowProcW      = user32DLL.NewProc("DefWindowProcW")
	procShowWindow          = user32DLL.NewProc("ShowWindow")
	procUpdateWindow        = user32DLL.NewProc("UpdateWindow")
	procGetMessageW         = user32DLL.NewProc("GetMessageW")
	procTranslateMessage    = user32DLL.NewProc("TranslateMessage")
	procDispatchMessageW    = user32DLL.NewProc("DispatchMessageW")
	procPostQuitMessage     = user32DLL.NewProc("PostQuitMessage")
	procSendMessageW        = user32DLL.NewProc("SendMessageW")
	procSetWindowTextW      = user32DLL.NewProc("SetWindowTextW")
	procDestroyWindow       = user32DLL.NewProc("DestroyWindow")
	procMessageBoxW         = user32DLL.NewProc("MessageBoxW")
	procCreatePopupMenu     = user32DLL.NewProc("CreatePopupMenu")
	procAppendMenuW         = user32DLL.NewProc("AppendMenuW")
	procTrackPopupMenu      = user32DLL.NewProc("TrackPopupMenu")
	procDestroyMenu         = user32DLL.NewProc("DestroyMenu")
	procGetCursorPos        = user32DLL.NewProc("GetCursorPos")
	procSetForegroundWindow = user32DLL.NewProc("SetForegroundWindow")
	procLoadIconW           = user32DLL.NewProc("LoadIconW")
	procLoadCursorW         = user32DLL.NewProc("LoadCursorW")
	procGetSystemMetrics    = user32DLL.NewProc("GetSystemMetrics")

	procGetModuleHandleW = kernel32DLL.NewProc("GetModuleHandleW")
	procCreateFontW      = gdi32DLL.NewProc("CreateFontW")
	procCreateSolidBrush = gdi32DLL.NewProc("CreateSolidBrush")
)

var (
	hBackgroundBrush uintptr
)

const (
	WM_DESTROY       = 0x0002
	WM_CLOSE         = 0x0010
	WM_COMMAND       = 0x0111
	WM_SYSCOMMAND    = 0x0112
	WM_APP           = 0x8000
	WM_TRAYICON      = WM_APP + 1
	WM_RBUTTONUP     = 0x0205
	WM_LBUTTONUP     = 0x0202
	WM_LBUTTONDBLCLK = 0x0203
	SC_MINIMIZE      = 0xF020

	WS_OVERLAPPED   = 0x00000000
	WS_CAPTION      = 0x00C00000
	WS_SYSMENU      = 0x00080000
	WS_MINIMIZEBOX  = 0x00020000
	WS_VISIBLE      = 0x10000000
	WS_CHILD        = 0x40000000
	WS_TABSTOP      = 0x00010000
	WS_BORDER       = 0x00800000

	SW_HIDE        = 0
	SW_SHOWNORMAL  = 1
	SW_RESTORE     = 9

	NIM_ADD     = 0x00000000
	NIM_MODIFY  = 0x00000001
	NIM_DELETE  = 0x00000002
	NIF_MESSAGE = 0x00000001
	NIF_ICON    = 0x00000002
	NIF_TIP     = 0x00000004
	NIF_INFO    = 0x00000010
	NIIF_INFO   = 0x00000001

	MF_STRING    = 0x00000000
	MF_SEPARATOR = 0x00000800

	MB_OK              = 0x00000000
	MB_ICONINFORMATION = 0x00000040
	MB_ICONWARNING     = 0x00000030
	MB_ICONERROR       = 0x00000010

	ID_BTN_OPEN_BROWSER = 1001
	ID_BTN_OPEN_APP     = 1002
	ID_BTN_TOGGLE       = 1003
	ID_BTN_RESTART      = 1004
	ID_BTN_FOLDER       = 1005
	ID_BTN_MINIMIZE     = 1006

	ID_TRAY_OPEN_BROWSER = 2001
	ID_TRAY_OPEN_APP     = 2002
	ID_TRAY_SHOW         = 2003
	ID_TRAY_RESTART      = 2004
	ID_TRAY_STOP         = 2005
	ID_TRAY_EXIT         = 2006
)

type WNDCLASSEXW struct {
	cbSize        uint32
	style         uint32
	lpfnWndProc   uintptr
	cbClsExtra    int32
	cbWndExtra    int32
	hInstance     uintptr
	hIcon         uintptr
	hCursor       uintptr
	hbrBackground uintptr
	lpszMenuName  *uint16
	lpszClassName *uint16
	hIconSm       uintptr
}

type POINT struct {
	X int32
	Y int32
}

type MSG struct {
	hwnd    uintptr
	message uint32
	wParam  uintptr
	lParam  uintptr
	time    uint32
	pt      POINT
}

type NOTIFYICONDATAW struct {
	cbSize            uint32
	hWnd              uintptr
	uID               uint32
	uFlags            uint32
	uCallbackMessage  uint32
	hIcon             uintptr
	szTip             [128]uint16
	dwState           uint32
	dwStateMask       uint32
	szInfo            [256]uint16
	uTimeoutOrVersion uint32
	szInfoTitle       [64]uint16
	dwInfoFlags       uint32
	guidItem          [16]byte
	hBalloonIcon      uintptr
}

var (
	appHwnd     uintptr
	hFontNormal uintptr
	hFontBold   uintptr
	hFontHeader uintptr
	hAppIcon    uintptr
	trayData    NOTIFYICONDATAW

	lblStatus   uintptr
	lblUrlLocal uintptr
	lblUrlLAN   uintptr
	lblPhpBin   uintptr
	btnToggle   uintptr

	globalSM      *ServerManager
	globalBaseDir string
)

func utf16Ptr(s string) *uint16 {
	p, _ := syscall.UTF16PtrFromString(s)
	return p
}

func showMsg(title, text string, iconType uint32) {
	procMessageBoxW.Call(appHwnd, uintptr(unsafe.Pointer(utf16Ptr(text))), uintptr(unsafe.Pointer(utf16Ptr(title))), uintptr(iconType))
}

func setupFonts() {
	segoe := utf16Ptr("Segoe UI")
	hFontNormal, _, _ = procCreateFontW.Call(15, 0, 0, 0, 400, 0, 0, 0, 1, 0, 0, 0, 0, uintptr(unsafe.Pointer(segoe)))
	hFontBold, _, _ = procCreateFontW.Call(15, 0, 0, 0, 700, 0, 0, 0, 1, 0, 0, 0, 0, uintptr(unsafe.Pointer(segoe)))
	hFontHeader, _, _ = procCreateFontW.Call(18, 0, 0, 0, 800, 0, 0, 0, 1, 0, 0, 0, 0, uintptr(unsafe.Pointer(segoe)))
}

func setControlFont(hwnd uintptr, font uintptr) {
	procSendMessageW.Call(hwnd, 0x0030, font, 1) // WM_SETFONT
}

func initTrayIcon(hwnd uintptr) {
	trayData.cbSize = uint32(unsafe.Sizeof(trayData))
	trayData.hWnd = hwnd
	trayData.uID = 1
	trayData.uFlags = NIF_MESSAGE | NIF_ICON | NIF_TIP
	trayData.uCallbackMessage = WM_TRAYICON
	trayData.hIcon = hAppIcon

	tip := fmt.Sprintf("NODERA Billing Server (%d)", globalSM.Port)
	copyUtf16Tip(&trayData.szTip, tip)

	procShellNotifyIconW.Call(NIM_ADD, uintptr(unsafe.Pointer(&trayData)))
}

func updateTrayTip(tip string) {
	copyUtf16Tip(&trayData.szTip, tip)
	procShellNotifyIconW.Call(NIM_MODIFY, uintptr(unsafe.Pointer(&trayData)))
}

func showTrayBalloon(title, message string) {
	trayData.uFlags |= NIF_INFO
	copyUtf16Info(&trayData.szInfoTitle, title)
	copyUtf16Info256(&trayData.szInfo, message)
	trayData.dwInfoFlags = NIIF_INFO
	procShellNotifyIconW.Call(NIM_MODIFY, uintptr(unsafe.Pointer(&trayData)))
}

func removeTrayIcon() {
	procShellNotifyIconW.Call(NIM_DELETE, uintptr(unsafe.Pointer(&trayData)))
}

func copyUtf16Tip(dst *[128]uint16, src string) {
	chars, _ := syscall.UTF16FromString(src)
	copy(dst[:], chars)
}

func copyUtf16Info(dst *[64]uint16, src string) {
	chars, _ := syscall.UTF16FromString(src)
	copy(dst[:], chars)
}

func copyUtf16Info256(dst *[256]uint16, src string) {
	chars, _ := syscall.UTF16FromString(src)
	copy(dst[:], chars)
}

func showTrayMenu(hwnd uintptr) {
	hMenu, _, _ := procCreatePopupMenu.Call()
	defer procDestroyMenu.Call(hMenu)

	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_OPEN_BROWSER, uintptr(unsafe.Pointer(utf16Ptr("🌐 Buka Billing di Browser"))))
	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_OPEN_APP, uintptr(unsafe.Pointer(utf16Ptr("💻 Buka Mode Aplikasi (Desktop)"))))
	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_SHOW, uintptr(unsafe.Pointer(utf16Ptr("📋 Tampilkan Panel Kontrol"))))
	procAppendMenuW.Call(hMenu, MF_SEPARATOR, 0, 0)
	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_RESTART, uintptr(unsafe.Pointer(utf16Ptr("🔄 Restart Server"))))
	if globalSM.IsRunning {
		procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_STOP, uintptr(unsafe.Pointer(utf16Ptr("🛑 Hentikan Server"))))
	} else {
		procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_STOP, uintptr(unsafe.Pointer(utf16Ptr("🚀 Jalankan Server"))))
	}
	procAppendMenuW.Call(hMenu, MF_SEPARATOR, 0, 0)
	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_EXIT, uintptr(unsafe.Pointer(utf16Ptr("❌ Keluar"))))

	var pt POINT
	procGetCursorPos.Call(uintptr(unsafe.Pointer(&pt)))
	procSetForegroundWindow.Call(hwnd)
	procTrackPopupMenu.Call(hMenu, 0x0002|0x0020, uintptr(pt.X), uintptr(pt.Y), 0, hwnd, 0)
}

func updateUIState() {
	if globalSM.IsRunning {
		procSetWindowTextW.Call(lblStatus, uintptr(unsafe.Pointer(utf16Ptr(fmt.Sprintf("● STATUS: SERVER BERJALAN AKTIF (Port %d)", globalSM.Port)))))
		procSetWindowTextW.Call(lblUrlLocal, uintptr(unsafe.Pointer(utf16Ptr(fmt.Sprintf("http://127.0.0.1:%d/login", globalSM.Port)))))
		procSetWindowTextW.Call(lblUrlLAN, uintptr(unsafe.Pointer(utf16Ptr(fmt.Sprintf("http://%s:%d/login", globalSM.LocalIP, globalSM.Port)))))
		procSetWindowTextW.Call(btnToggle, uintptr(unsafe.Pointer(utf16Ptr("🛑 Hentikan Server"))))
		updateTrayTip(fmt.Sprintf("NODERA Billing: Aktif (Port %d)", globalSM.Port))
	} else {
		procSetWindowTextW.Call(lblStatus, uintptr(unsafe.Pointer(utf16Ptr("○ STATUS: SERVER BERHENTI"))))
		procSetWindowTextW.Call(btnToggle, uintptr(unsafe.Pointer(utf16Ptr("🚀 Jalankan Server"))))
		updateTrayTip("NODERA Billing: Berhenti")
	}

	phpText := "PHP: " + globalSM.PhpBinary
	if len(phpText) > 65 {
		phpText = "PHP: ..." + phpText[len(phpText)-60:]
	}
	procSetWindowTextW.Call(lblPhpBin, uintptr(unsafe.Pointer(utf16Ptr(phpText))))
}

func toggleServer() {
	if globalSM.IsRunning {
		globalSM.Stop()
	} else {
		if err := globalSM.Start(); err != nil {
			showMsg("Gagal Menjalankan Server", fmt.Sprintf("Error: %v", err), MB_ICONERROR)
		}
	}
	updateUIState()
}

func openBillingBrowser() {
	if !globalSM.IsRunning {
		if err := globalSM.Start(); err != nil {
			showMsg("Gagal Menjalankan Server", fmt.Sprintf("Error: %v", err), MB_ICONERROR)
			return
		}
		updateUIState()
	}
	url := fmt.Sprintf("http://127.0.0.1:%d/login", globalSM.Port)
	_ = OpenBrowser(url)
}

func openBillingApp() {
	if !globalSM.IsRunning {
		if err := globalSM.Start(); err != nil {
			showMsg("Gagal Menjalankan Server", fmt.Sprintf("Error: %v", err), MB_ICONERROR)
			return
		}
		updateUIState()
	}
	url := fmt.Sprintf("http://127.0.0.1:%d/login", globalSM.Port)
	_, _ = OpenAppWindow(url)
}

func wndProc(hwnd uintptr, msg uint32, wParam, lParam uintptr) uintptr {
	switch msg {
	case WM_SYSCOMMAND:
		if wParam&0xFFF0 == SC_MINIMIZE {
			procShowWindow.Call(hwnd, SW_HIDE)
			showTrayBalloon("NODERA Billing Server", fmt.Sprintf("Server berjalan di background pada port %d. Klik icon tray untuk membuka kembali.", globalSM.Port))
			return 0
		}

	case WM_COMMAND:
		ctrlId := int(wParam & 0xFFFF)
		switch ctrlId {
		case ID_BTN_OPEN_BROWSER, ID_TRAY_OPEN_BROWSER:
			openBillingBrowser()
		case ID_BTN_OPEN_APP, ID_TRAY_OPEN_APP:
			openBillingApp()
		case ID_BTN_TOGGLE, ID_TRAY_STOP:
			toggleServer()
		case ID_BTN_RESTART, ID_TRAY_RESTART:
			globalSM.Stop()
			time.Sleep(300 * time.Millisecond)
			if err := globalSM.Start(); err != nil {
				showMsg("Gagal Restart", fmt.Sprintf("Error: %v", err), MB_ICONERROR)
			}
			updateUIState()
			showTrayBalloon("Server Direstart", fmt.Sprintf("NODERA Billing aktif kembali di port %d.", globalSM.Port))
		case ID_BTN_FOLDER:
			_ = OpenBrowser(globalBaseDir)
		case ID_BTN_MINIMIZE:
			procShowWindow.Call(hwnd, SW_HIDE)
			showTrayBalloon("NODERA Billing Server", "Panel disembunyikan ke System Tray.")
		case ID_TRAY_SHOW:
			procShowWindow.Call(hwnd, SW_RESTORE)
			procSetForegroundWindow.Call(hwnd)
		case ID_TRAY_EXIT:
			globalSM.Stop()
			removeTrayIcon()
			procDestroyWindow.Call(hwnd)
		}
		return 0

	case WM_TRAYICON:
		switch lParam {
		case WM_RBUTTONUP:
			showTrayMenu(hwnd)
		case WM_LBUTTONDBLCLK, WM_LBUTTONUP:
			procShowWindow.Call(hwnd, SW_RESTORE)
			procSetForegroundWindow.Call(hwnd)
		}
		return 0

	case WM_CLOSE:
		globalSM.Stop()
		removeTrayIcon()
		procDestroyWindow.Call(hwnd)
		return 0

	case WM_DESTROY:
		procPostQuitMessage.Call(0)
		return 0
	}

	ret, _, _ := procDefWindowProcW.Call(hwnd, uintptr(msg), wParam, lParam)
	return ret
}

// CreateServerControlWindow initializes and displays the native Win32 control window
func CreateServerControlWindow(baseDir string, sm *ServerManager) {
	runtime.LockOSThread()
	defer runtime.UnlockOSThread()

	globalSM = sm
	globalBaseDir = baseDir

	setupFonts()

	hInstance, _, _ := procGetModuleHandleW.Call(0)
	hAppIcon, _, _ = procLoadIconW.Call(hInstance, 1) // Loaded from winres icon resource
	if hAppIcon == 0 {
		hAppIcon, _, _ = procLoadIconW.Call(0, 32512) // IDI_APPLICATION
	}
	hCursor, _, _ := procLoadCursorW.Call(0, 32512) // IDC_ARROW

	// Light neutral gray background (#F8F9FA)
	hBackgroundBrush, _, _ = procCreateSolidBrush.Call(0x00F8F9FA)

	className := utf16Ptr("NoderaBillingControlWindow2026")

	var wc WNDCLASSEXW
	wc.cbSize = uint32(unsafe.Sizeof(wc))
	wc.style = 0x0003 // CS_HREDRAW | CS_VREDRAW
	wc.lpfnWndProc = syscall.NewCallback(wndProc)
	wc.hInstance = hInstance
	wc.hIcon = hAppIcon
	wc.hIconSm = hAppIcon
	wc.hCursor = hCursor
	wc.hbrBackground = hBackgroundBrush
	wc.lpszClassName = className

	procRegisterClassExW.Call(uintptr(unsafe.Pointer(&wc)))

	winW := int32(560)
	winH := int32(430)

	screenW, _, _ := procGetSystemMetrics.Call(0)
	screenH, _, _ := procGetSystemMetrics.Call(1)
	posX := (int32(screenW) - winW) / 2
	posY := (int32(screenH) - winH) / 2

	title := utf16Ptr("NODERA Billing — Server Launcher & Control Panel")
	hwnd, _, _ := procCreateWindowExW.Call(
		0,
		uintptr(unsafe.Pointer(className)),
		uintptr(unsafe.Pointer(title)),
		WS_OVERLAPPED|WS_CAPTION|WS_SYSMENU|WS_MINIMIZEBOX|WS_VISIBLE,
		uintptr(posX), uintptr(posY),
		uintptr(winW), uintptr(winH),
		0, 0, hInstance, 0,
	)
	appHwnd = hwnd

	createControl := func(class, text string, style uint32, x, y, w, h int32, id int) uintptr {
		cName := utf16Ptr(class)
		cText := utf16Ptr(text)
		hCtrl, _, _ := procCreateWindowExW.Call(
			0,
			uintptr(unsafe.Pointer(cName)),
			uintptr(unsafe.Pointer(cText)),
			uintptr(style|WS_CHILD|WS_VISIBLE),
			uintptr(x), uintptr(y), uintptr(w), uintptr(h),
			hwnd, uintptr(id), hInstance, 0,
		)
		return hCtrl
	}

	// 1. Header Banner
	hHeader := createControl("STATIC", "NODERA BILLING — ISP & RT/RW NET", 0x0000, 24, 18, 510, 26, 0)
	setControlFont(hHeader, hFontHeader)

	hSubHeader := createControl("STATIC", "Platform Manajemen ISP & Tagihan Pelanggan Multi-Router", 0x0000, 24, 46, 510, 20, 0)
	setControlFont(hSubHeader, hFontNormal)

	// 2. Status Badge
	lblStatus = createControl("STATIC", "● STATUS: MEMERIKSA ENGINE...", 0x0000, 24, 76, 510, 22, 0)
	setControlFont(lblStatus, hFontBold)

	// Group Box: Server Endpoints
	hGroup := createControl("BUTTON", " Alamat Akses Server ", 0x0007, 20, 106, 505, 125, 0) // BS_GROUPBOX
	setControlFont(hGroup, hFontNormal)

	hLblLocal := createControl("STATIC", "Akses Komputer (Lokal):", 0x0000, 36, 132, 170, 20, 0)
	setControlFont(hLblLocal, hFontBold)

	lblUrlLocal = createControl("EDIT", "http://127.0.0.1:8080/login", WS_BORDER|0x0800, 215, 130, 290, 24, 0) // ES_READONLY
	setControlFont(lblUrlLocal, hFontNormal)

	hLblLAN := createControl("STATIC", "Akses Jaringan (HP/Kasir):", 0x0000, 36, 164, 170, 20, 0)
	setControlFont(hLblLAN, hFontBold)

	lblUrlLAN = createControl("EDIT", "http://127.0.0.1:8080/login", WS_BORDER|0x0800, 215, 162, 290, 24, 0) // ES_READONLY
	setControlFont(lblUrlLAN, hFontNormal)

	lblPhpBin = createControl("STATIC", "PHP: Mendeteksi...", 0x0000, 36, 196, 470, 20, 0)
	setControlFont(lblPhpBin, hFontNormal)

	// 3. Action Buttons
	btnOpenBrowser := createControl("BUTTON", "🌐 Buka di Browser", WS_TABSTOP, 20, 245, 245, 42, ID_BTN_OPEN_BROWSER)
	setControlFont(btnOpenBrowser, hFontBold)

	btnOpenApp := createControl("BUTTON", "💻 Buka Mode Desktop App", WS_TABSTOP, 280, 245, 245, 42, ID_BTN_OPEN_APP)
	setControlFont(btnOpenApp, hFontBold)

	btnToggle = createControl("BUTTON", "🛑 Hentikan Server", WS_TABSTOP, 20, 298, 160, 36, ID_BTN_TOGGLE)
	setControlFont(btnToggle, hFontNormal)

	btnRestart := createControl("BUTTON", "🔄 Restart Server", WS_TABSTOP, 192, 298, 160, 36, ID_BTN_RESTART)
	setControlFont(btnRestart, hFontNormal)

	btnFolder := createControl("BUTTON", "📁 Folder Billing", WS_TABSTOP, 365, 298, 160, 36, ID_BTN_FOLDER)
	setControlFont(btnFolder, hFontNormal)

	btnMin := createControl("BUTTON", "⏬ Minimize ke System Tray", WS_TABSTOP, 20, 344, 505, 30, ID_BTN_MINIMIZE)
	setControlFont(btnMin, hFontNormal)

	initTrayIcon(hwnd)

	// Auto-start server if not already running
	if !sm.IsRunning {
		go func() {
			if err := sm.Start(); err != nil {
				showMsg("Perhatian", fmt.Sprintf("Gagal memulai server otomatis: %v", err), MB_ICONWARNING)
			}
			updateUIState()
		}()
	} else {
		updateUIState()
	}

	procShowWindow.Call(hwnd, SW_SHOWNORMAL)
	procUpdateWindow.Call(hwnd)

	var m MSG
	for {
		ret, _, _ := procGetMessageW.Call(uintptr(unsafe.Pointer(&m)), 0, 0, 0)
		if ret == 0 || int32(ret) == -1 {
			break
		}
		procTranslateMessage.Call(uintptr(unsafe.Pointer(&m)))
		procDispatchMessageW.Call(uintptr(unsafe.Pointer(&m)))
	}
}
