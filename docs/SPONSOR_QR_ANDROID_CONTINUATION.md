# Sponsor QR + Android Scanner — Continuation

Checkpoint: 2026-10-23
Repository: `moneermax/Ahl-El-Kheir-charity-management-system`
Branch: `main`
Status: SOURCE IMPLEMENTATION STARTED; PHP/Android build and runtime acceptance are not yet verified.

## Agreed behavior

- Every sponsor profile displays a locally generated QR code.
- The QR contains only the existing sponsor-profile URL; it does not encode phone, email, address, or other private fields.
- Scanning opens `modules/sponsors/view.php?id={id}`, preserving its existing login, role checks, and supervisor scope checks.
- The sponsor profile displays the distinct count of children with an active sponsorship for that sponsor.
- Each sponsorship row on `modules/families/orphan_form.php` and `modules/families/orphan_profile.php` displays the linked sponsor's QR.
- QR generation uses the TCPDF 2D barcode implementation already bundled in the repository. No new QR generation dependency or schema migration is intended.

## Android companion APK

Source path: `android/sponsor-qr-scanner/`

- Native Android camera scanning uses ZXing Android Embedded.
- The scanned sponsor URL opens inside a WebView, so the existing website login/session and PHP authorization remain authoritative.
- The app stores the configured server base URL only. It rejects scanned URLs that are not sponsor-profile URLs on that same configured origin/path.
- The debug build allows HTTP only for localhost/private-network addresses for local testing. Release configuration requires HTTPS.
- GitHub Actions workflow: `.github/workflows/build-sponsor-qr-scanner.yml`; expected artifact name: `ahl-el-kheir-sponsor-qr-scanner-debug-apk`.
- The artifact is a debug-signed APK for testing, not a production release signing package.

## Source changes currently on main

- `modules/sponsors/lib_sponsor_qr.php` — locally generated SVG QR data URI helper.
- `modules/sponsors/view.php` — QR card and active sponsored-orphan count.
- `modules/families/orphan_form.php` — QR beside each sponsor on the printable orphan form.
- `modules/families/orphan_profile.php` — QR column for linked sponsors.
- `android/sponsor-qr-scanner/` — native Android project.
- `.github/workflows/build-sponsor-qr-scanner.yml` — debug APK build workflow.

## Testing plan

### PHP and existing application

1. Pull `main`.
2. Run `php -l` on:
   - `modules/sponsors/lib_sponsor_qr.php`
   - `modules/sponsors/view.php`
   - `modules/families/orphan_form.php`
   - `modules/families/orphan_profile.php`
3. Open the existing app via Apache and verify the sponsor profile QR renders, can be scanned, and opens the same sponsor profile.
4. Compare the displayed active sponsored-orphan count with a read-only SQL count using `COUNT(DISTINCT child_id)` for that sponsor where `sponsorships.status='active'`.
5. Open an orphan form with one and multiple active/paused sponsorships; confirm each linked sponsor has the correct QR and that printing keeps it scannable.
6. Test a supervisor with an in-scope and out-of-scope sponsor; direct QR access must not bypass the existing sponsor scope. Test a role not permitted to view sponsors; it must not receive sponsor details.

### APK

1. Confirm the GitHub Actions APK build completes and download the workflow artifact.
2. Install the APK on an Android test phone.
3. For local physical-phone testing, put the phone and XAMPP computer on the same Wi-Fi network. Open the system on the computer using `http://LAN-IP:8081/AhlElKheir/`, not `localhost`; configure the APK with that same base URL. Ensure Apache and Windows Firewall permit the test connection.
4. Sign in inside the APK, scan a sponsor QR, and verify the authorized sponsor profile opens.
5. Test invalid QR, another host, logged-out flow, unauthorized role, camera permission denial, network loss, and back navigation.
6. For an Android emulator, the host computer is typically `http://10.0.2.2:8081/AhlElKheir/`.
7. Repeat the complete test on the deployed HTTPS host before distributing a production-signed release APK.

## Current evidence boundary

No local PHP lint, Android compilation, physical-device scan, Apache/browser, database-count comparison, or authorization runtime test has been reported yet. Do not mark this feature complete until those checks are evidenced. Do not apply a database migration unless later inspection proves one is needed. Do not reopen the separate document archive task except when explicitly requested.


## 2026-10-23 — CI SDK setup failure and workflow adjustment

The first GitHub Actions run passed all four PHP lint checks and Java 17 setup, then failed inside `android-actions/setup-android@v3`. The visible log ended with `/usr/local/lib/android/sdk/cmdline-tools/16.0/bin/sdkmanager` exiting with code 1 at “10% Computing updates...”. The supplied excerpt does not reveal the SDK manager's underlying diagnostic, so the exact network/repository cause is not proven.

Workflow commit `84cb9fe4621b0043c8546da24f74530e49c70749` removes the extra Android SDK setup and package-install calls and instead checks for Android 35 and Build Tools 35.0.0 already present on the GitHub-hosted Ubuntu runner. This avoids the failing SDK-manager update path if those packages are present. **This adjustment is not yet verified**; wait for the new Actions run. If the package-directory check fails, inspect the runner image's installed SDK packages and choose a supported preinstalled version rather than guessing.


## 2026-10-23 — Duplicate-class build diagnostic

The Android build reached Gradle task `:app:checkDebugDuplicateClasses` and failed, but the log excerpt supplied by the user did not name the duplicate classes. ZXing is only a hypothesis; do not change/remove dependencies until class names or dependency evidence identify the overlap.

Workflow commit `36f464b6d6975c65499498cb14c19742ba01745e` changes the build step to run `:app:checkDebugDuplicateClasses --stacktrace --info`, print matching error lines with context, and upload `duplicate-classes.log` as artifact `sponsor-qr-scanner-build-diagnostic` on failure. This diagnostic workflow has not yet been run/verified. Next action: open the newest Actions run and inspect the printed duplicate class names, or download the diagnostic artifact. Fix only the dependency conflict established by that evidence.


## 2026-10-23 — Kotlin duplicate-class root cause and targeted fix

Workflow job `120438823455` (run `39961045578`) confirmed that the duplicate classes are between `kotlin-stdlib:1.8.22` and the legacy `kotlin-stdlib-jdk7:1.6.21` / `kotlin-stdlib-jdk8:1.6.21` artifacts. Gradle dependency insight showed the old split artifacts arriving transitively through AndroidX lifecycle and `kotlinx-coroutines-android:1.6.4`; the newer standard library already contains those JDK extension classes.

Targeted fix committed on main: `777f9b180ce61dade8c96649a8138e7f4bb85880`. `android/sponsor-qr-scanner/app/build.gradle` now excludes only the obsolete `kotlin-stdlib-jdk7` and `kotlin-stdlib-jdk8` modules from Gradle configurations, retaining AppCompat and ZXing. This is a source-level fix; it is **not verified until the next GitHub Actions run passes duplicate-class validation and assembles the APK**.

Next: inspect the run triggered by this commit. If duplicate-class validation passes, verify APK artifact upload. Do not report the APK as built until that artifact exists.


## 2026-10-23 — BuildConfig compiler error

Actions job `120446032500` reached `:app:compileDebugJavaWithJavac` and failed because `MainActivity.java` references `BuildConfig.DEBUG` but no `BuildConfig` class was generated. The module uses Android Gradle Plugin `8.7.3`; BuildConfig generation is disabled by default in AGP 8 unless enabled explicitly. Main commit `ecdc34c19f1d5f5c370a95b3a902739693710b7f` adds `buildFeatures { buildConfig true }` in `app/build.gradle`. This diagnosis is based on the module's actual AGP version and source references; the fix still requires a new Actions run to verify Java compilation and APK assembly.


## 2026-10-23 — Sponsor QR host diagnosis and portrait scanner change

User confirmed that opening the existing system through the laptop LAN address and scanning the newly generated sponsor QR successfully opens the sponsor profile. The previously reported “رمز غير صالح” was caused by generating the QR while the website was opened at `localhost`; that URL points to the requesting device and does not match the phone scanner's configured laptop LAN host. No origin-validation bypass was added. For physical-phone testing, generate QR codes while browsing the site using the same LAN host/port/application path configured in the APK.

Portrait change committed to main:
- `android/sponsor-qr-scanner/app/src/main/AndroidManifest.xml`: lock `.MainActivity` to portrait.
- `android/sponsor-qr-scanner/app/src/main/java/org/ahlelkhier/qrscanner/MainActivity.java`: set ZXing `setOrientationLocked(true)`, so the capture screen follows the portrait-locked activity.
- `android/sponsor-qr-scanner/README.md`: document portrait behavior and the matching-host requirement.

Source changes are committed, but the updated APK and physical-device portrait behavior are not yet verified. The Android workflow should build a new debug APK from these commits. After it succeeds, install that artifact and verify scanning while holding the phone upright. Keep existing website authentication, sponsor permissions, supervisor scope checks, and same-origin validation unchanged.
