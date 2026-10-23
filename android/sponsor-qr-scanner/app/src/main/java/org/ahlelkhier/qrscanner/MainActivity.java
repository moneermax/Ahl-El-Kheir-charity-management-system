package org.ahlelkhier.qrscanner;

import android.annotation.SuppressLint;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Bundle;
import android.text.InputType;
import android.webkit.CookieManager;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;

import com.journeyapps.barcodescanner.ScanContract;
import com.journeyapps.barcodescanner.ScanOptions;

import java.util.Locale;
import java.util.regex.Pattern;

public class MainActivity extends AppCompatActivity {
    private static final String PREFS_NAME = "ahl_el_kheir_scanner";
    private static final String PREF_BASE_URL = "server_base_url";

    private WebView webView;
    private TextView connectionStatus;
    private String baseUrl = "";
    private String pendingSponsorUrl;
    private boolean pendingSponsorRetried;
    private ActivityResultLauncher<ScanOptions> scanLauncher;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.systemWebView);
        connectionStatus = findViewById(R.id.connectionStatus);
        Button scanButton = findViewById(R.id.scanButton);
        Button serverButton = findViewById(R.id.serverButton);

        scanLauncher = registerForActivityResult(new ScanContract(), result -> {
            String contents = result.getContents();
            if (contents == null) return;

            Uri scanned = Uri.parse(contents);
            if (!isSponsorDetailUrl(scanned)) {
                Toast.makeText(this, "رمز غير صالح. امسح رمز كفيل صادر من نظام أهل الخير.", Toast.LENGTH_LONG).show();
                return;
            }

            pendingSponsorUrl = scanned.toString();
            pendingSponsorRetried = false;
            webView.loadUrl(pendingSponsorUrl);
        });

        CookieManager cookies = CookieManager.getInstance();
        cookies.setAcceptCookie(true);
        cookies.setAcceptThirdPartyCookies(webView, false);

        webView.getSettings().setJavaScriptEnabled(true);
        webView.getSettings().setDomStorageEnabled(true);
        webView.getSettings().setAllowFileAccess(false);
        webView.getSettings().setAllowContentAccess(false);
        webView.getSettings().setMixedContentMode(android.webkit.WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri target = request.getUrl();
                if (isInsideConfiguredApp(target)) return false;
                Toast.makeText(MainActivity.this, "تم حظر فتح رابط خارج نظام أهل الخير.", Toast.LENGTH_SHORT).show();
                return true;
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                connectionStatus.setText("الخادم: " + baseUrl);
                if (pendingSponsorUrl == null) return;

                if (url.equals(pendingSponsorUrl)) {
                    pendingSponsorUrl = null;
                    pendingSponsorRetried = false;
                    return;
                }

                if (isLoginPage(Uri.parse(url))) return;

                if (!pendingSponsorRetried) {
                    pendingSponsorRetried = true;
                    String target = pendingSponsorUrl;
                    webView.postDelayed(() -> webView.loadUrl(target), 300);
                } else {
                    pendingSponsorUrl = null;
                    pendingSponsorRetried = false;
                    Toast.makeText(MainActivity.this,
                            "تعذر فتح ملف الكفيل. تحقق من تسجيل الدخول وصلاحيات حسابك.",
                            Toast.LENGTH_LONG).show();
                }
            }
        });

        scanButton.setOnClickListener(v -> startScan());
        serverButton.setOnClickListener(v -> showServerDialog(baseUrl, !baseUrl.isEmpty()));

        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        baseUrl = prefs.getString(PREF_BASE_URL, "");
        if (baseUrl.isEmpty()) {
            showServerDialog("", false);
        } else if (isValidBaseUrl(baseUrl)) {
            loadHome();
        } else {
            baseUrl = "";
            showServerDialog("", false);
        }
    }

    private void startScan() {
        if (baseUrl.isEmpty()) {
            showServerDialog("", false);
            return;
        }
        ScanOptions options = new ScanOptions();
        options.setDesiredBarcodeFormats(ScanOptions.QR_CODE);
        options.setPrompt("وجّه الكاميرا إلى رمز QR للكفيل");
        options.setBeepEnabled(true);
        // MainActivity is portrait-locked, so lock the capture screen to portrait too.
        options.setOrientationLocked(true);
        scanLauncher.launch(options);
    }

    private void showServerDialog(String currentValue, boolean canCancel) {
        EditText input = new EditText(this);
        input.setSingleLine(true);
        input.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_URI);
        input.setText(currentValue);
        input.setHint(BuildConfig.DEBUG
                ? "http://192.168.1.10:8081/AhlElKheir/"
                : "https://example.org/AhlElKheir/");

        AlertDialog.Builder builder = new AlertDialog.Builder(this)
                .setTitle("عنوان نظام أهل الخير")
                .setMessage(BuildConfig.DEBUG
                        ? "أدخل رابط النظام. للاختبار المحلي استخدم عنوان IP للكمبيوتر على الشبكة، وليس localhost من الهاتف."
                        : "أدخل رابط النظام المستضاف باستخدام HTTPS.")
                .setView(input)
                .setPositiveButton("حفظ", (dialog, which) -> {
                    String normalized = normalizeBaseUrl(input.getText().toString());
                    if (normalized == null || !isValidBaseUrl(normalized)) {
                        Toast.makeText(this,
                                "الرابط غير صالح. استخدم HTTPS، أو عنوان IP محلياً في نسخة الاختبار فقط.",
                                Toast.LENGTH_LONG).show();
                        showServerDialog(input.getText().toString(), !baseUrl.isEmpty());
                        return;
                    }
                    baseUrl = normalized;
                    getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
                            .edit().putString(PREF_BASE_URL, baseUrl).apply();
                    pendingSponsorUrl = null;
                    pendingSponsorRetried = false;
                    loadHome();
                });

        if (canCancel && !baseUrl.isEmpty()) {
            builder.setNegativeButton("إلغاء", (dialog, which) -> { });
        }
        AlertDialog dialog = builder.create();
        dialog.setCancelable(canCancel && !baseUrl.isEmpty());
        dialog.show();
    }

    private String normalizeBaseUrl(String value) {
        String raw = value == null ? "" : value.trim();
        if (raw.isEmpty() || raw.contains(" ") || raw.contains("\n") || raw.contains("\r")) return null;

        Uri uri = Uri.parse(raw);
        if (uri.getScheme() == null || uri.getHost() == null
                || uri.getUserInfo() != null || uri.getQuery() != null || uri.getFragment() != null) return null;

        String scheme = uri.getScheme().toLowerCase(Locale.ROOT);
        if (!scheme.equals("https") && !scheme.equals("http")) return null;

        String path = uri.getPath();
        if (path == null || path.isEmpty()) path = "/";
        if (!path.endsWith("/")) path += "/";
        return scheme + "://" + uri.getAuthority() + path;
    }

    private boolean isValidBaseUrl(String value) {
        Uri uri = Uri.parse(value);
        if (uri.getHost() == null || uri.getUserInfo() != null
                || uri.getQuery() != null || uri.getFragment() != null
                || uri.getPath() == null || !uri.getPath().endsWith("/")) return false;

        if ("https".equalsIgnoreCase(uri.getScheme())) return true;
        return BuildConfig.DEBUG
                && "http".equalsIgnoreCase(uri.getScheme())
                && isLocalOrPrivateHost(uri.getHost());
    }

    private boolean isLocalOrPrivateHost(String hostValue) {
        String host = hostValue == null ? "" : hostValue.toLowerCase(Locale.ROOT);
        if (host.equals("localhost") || host.equals("10.0.2.2") || host.equals("::1")) return true;
        if (!Pattern.matches("^(?:\\d{1,3}\\.){3}\\d{1,3}$", host)) return false;

        String[] octets = host.split("\\.");
        int a = Integer.parseInt(octets[0]);
        int b = Integer.parseInt(octets[1]);
        int c = Integer.parseInt(octets[2]);
        int d = Integer.parseInt(octets[3]);
        if (a > 255 || b > 255 || c > 255 || d > 255) return false;
        return a == 10 || a == 127 || (a == 192 && b == 168)
                || (a == 172 && b >= 16 && b <= 31)
                || (a == 169 && b == 254);
    }

    private void loadHome() {
        if (webView != null && !baseUrl.isEmpty()) {
            connectionStatus.setText("جارٍ الاتصال بالخادم...");
            webView.loadUrl(baseUrl);
        }
    }

    private boolean isInsideConfiguredApp(Uri target) {
        if (baseUrl.isEmpty()) return false;
        Uri base = Uri.parse(baseUrl);
        if (target.getScheme() == null || target.getHost() == null) return false;
        if (!target.getScheme().equalsIgnoreCase(base.getScheme())
                || !target.getHost().equalsIgnoreCase(base.getHost())
                || target.getPort() != base.getPort()) return false;
        String basePath = base.getPath() == null ? "/" : base.getPath();
        if (!basePath.endsWith("/")) basePath += "/";
        String targetPath = target.getPath() == null ? "/" : target.getPath();
        return targetPath.startsWith(basePath);
    }

    private boolean isSponsorDetailUrl(Uri target) {
        if (!isInsideConfiguredApp(target)) return false;
        Uri base = Uri.parse(baseUrl);
        String basePath = base.getPath() == null ? "/" : base.getPath();
        if (!basePath.endsWith("/")) basePath += "/";
        String expectedPath = basePath + "modules/sponsors/view.php";
        if (!expectedPath.equals(target.getPath())) return false;

        String id = target.getQueryParameter("id");
        return id != null && id.matches("^[1-9][0-9]*$");
    }

    private boolean isLoginPage(Uri uri) {
        Uri base = Uri.parse(baseUrl);
        String basePath = base.getPath() == null ? "/" : base.getPath();
        if (!basePath.endsWith("/")) basePath += "/";
        String path = uri.getPath() == null ? "/" : uri.getPath();
        return path.equals(basePath) || path.equals(basePath + "index.php");
    }

    @Override
    @SuppressWarnings("deprecation")
    public void onBackPressed() {
        if (webView != null && webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }
}
