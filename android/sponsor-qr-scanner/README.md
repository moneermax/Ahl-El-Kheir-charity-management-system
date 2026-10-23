# Ahl El Kheir Sponsor QR Scanner (Android)

This is a companion APK for the existing procedural-PHP Ahl El Kheir system. It is not a replacement application.

## What it does

- Uses the phone camera and the bundled ZXing scanner to read QR codes.
- Accepts only sponsor-profile URLs on the configured Ahl El Kheir server.
- Opens the existing sponsor profile inside an in-app WebView.
- Uses the existing website login/session and server-side role/scope checks.
- Stores only the configured server base URL locally; sponsor details and credentials are not stored by the scanner code.
- QR contents are URLs, not sponsor contact information.

## Build and install

The GitHub Actions workflow builds an installable, debug-signed APK and publishes it as a workflow artifact named `ahl-el-kheir-sponsor-qr-scanner-debug-apk`. Download the artifact from the successful workflow run, extract the APK, and install it on an Android test phone. A debug APK is for testing; a separately signed release APK should be prepared before general production distribution.

## First launch

1. Enter the complete base URL of the existing system, including its application path and trailing slash, for example `https://your-domain.example/AhlElKheir/`.
2. Sign in using an existing authorized Ahl El Kheir account inside the app.
3. Tap **مسح QR** and scan a QR displayed on a sponsor profile or orphan form.
4. The app checks that the QR is a sponsor-profile URL on the configured server before opening it.
5. The PHP sponsor profile remains responsible for authentication and permission checks.

## Testing before public hosting

A physical Android phone and the XAMPP computer can test together on the same Wi-Fi network:

1. Allow Apache through the Windows firewall for the local test network.
2. Find the computer's LAN IPv4 address.
3. Open the existing system from the computer using `http://LAN-IP:8081/AhlElKheir/` instead of `localhost`.
4. In the APK's server settings, enter the same LAN URL. The QR must be generated while the system is opened through that same host so its encoded URL matches the configured server.
5. Sign in, open a sponsor profile, and scan its QR. Test a role that is allowed to view the sponsor and a role that is not.
6. For an Android emulator, the host computer is usually reachable at `http://10.0.2.2:8081/AhlElKheir/`.

The debug build permits HTTP only for localhost/private-network IP addresses to support controlled local testing. The release build requires HTTPS.

## Acceptance checklist

- [ ] APK installs and opens on a physical Android phone.
- [ ] Camera permission is requested and scanning succeeds.
- [ ] Invalid QR and QR URLs from another host are rejected.
- [ ] Existing website login works inside the app.
- [ ] An authorized role opens the sponsor profile.
- [ ] An unauthorized role cannot read sponsor details.
- [ ] Active sponsored-orphan count matches the database.
- [ ] QR appears on sponsor profile and each linked sponsor row on the orphan form/profile.
- [ ] The production HTTPS host is tested before release distribution.


## Phone scanning orientation and local QR host

- The app and camera capture screen are locked to portrait orientation; keep the phone upright while scanning.
- For a physical phone on local Wi-Fi, open the website on the computer using the computer's LAN IP (for example, `http://192.168.18.9:8081/AhlElKheir/`) before opening the sponsor profile and displaying its QR. Do not generate a phone-scannable QR from a `localhost` page: `localhost` points to the device doing the request, so the phone cannot use the laptop's `localhost` address.
- Keep the APK's configured base URL identical to the host, port, and application path used to generate the QR. The scanner intentionally rejects different hosts rather than weakening its origin checks.
