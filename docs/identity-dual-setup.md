# Automatic ID portrait + selfie matching (v4)

This version requires **local face-api.js AND Face++ Compare** to pass the same server-created ID portrait and camera-selfie crops. Any mismatch, missing setup, quota, timeout or provider error blocks/retries. It checks face similarity; document authenticity and certified liveness are not provided.

The current manual v2 mode stays active while v4 is staged. No old ID/selfie is sent to Face++; enrollment needs fresh consent. After activation there is no manual/local-only fallback. Historical submissions/audit remain intact; an old manual approval requires fresh dual enrollment for future submissions. Login changes are separate.

## 1. Create and check the provider account

The owner creates the account in the [official Face++ console](https://console.faceplusplus.com/). Do not send API keys in chat, commit them, put them in Obsidian or use a browser-side SDK. Confirm the actual account region, current free quota and terms before using it. The [global pricing page](https://www.faceplusplus.com/v2/pricing/) advertises free services, but this project does not promise unlimited/permanent free capacity or enable billing.

Read the actual [Compare contract](https://console.faceplusplus.com/documents/5679308) for the selected account. The prepared adapter posts `image_file1` and `image_file2` to the fixed regional `/facepp/v3/compare` endpoint. It requires exactly one face in `faces1` and `faces2`, numeric confidence and the numeric returned `thresholds['1e-5']`. Confidence must strictly exceed that threshold. No guessed 70/80 cutoff or averaging two engines. The console documentation rendered only a loading shell during implementation research; this exact schema/operating point and region still need account-side confirmation before readiness is true.

Check the [provider privacy policy](https://www.faceplusplus.com/privacy-policy/) and the displayed seller consent. Canonical face crops leave this server for external processing. The app sends neither full ID text nor public image URLs, and discards provider face/image tokens. Local evidence retention is separate from provider data handling. Do not infer a biometric accuracy or anti-spoofing guarantee from a few successful tests.

## 2. Save credentials locally

Use a PowerShell account with access to the protected private identity directory. This command prompts for hidden values, sends them through a local stdin pipe and merges only the dual fields into the existing private configuration:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\VNZ-TECH\tools\configure_identity_dual.ps1" -Region us
```

Use `-Region cn` only for an account/key actually issued for that region. The adapter permits only the fixed US/CN HTTPS endpoints; TLS certificate/hostname checks stay enabled, redirects and proxies are disabled, and time/size limits are fixed. If system CA trust is unavailable, repair trusted CA configuration locally; do not disable TLS verification.

The script preserves `enabled`, storage/key paths, cleanup settings and the existing mode. It sets `contract_confirmed` and `matching_validated` to **false**, even when replacing keys. It never creates a provider account, buys credits, uploads an image or activates dual mode. `tools/identity_dual_config.example.php` is a **nonsecret field reference**, not a replacement for the existing private file. Keep real values only in `C:\xampp\private\VNZ-TECH\identity\config.php` under its restricted permissions. Do not copy that file into the repository.

## 3. Establish readiness before activation

Before changing either readiness flag, record nonsecret results locally: selected account region/quota; official Compare schema/`1e-5` contract; successful additive migration/private encryption/cleanup/model health; and fresh, explicit owner-consented same-person and different-person checks through the actual crop/local/provider pipeline. Include low-quality/no/multiple-face and retry/unavailable cases. Do not process old captures, silently relax the local 0.45 policy, fabricate readiness, or store faces/descriptors/IDs/tokens/keys in task notes.

After those real checks, the authorized local owner/runner can set the two booleans in the existing private config to `true`. They are operator attestations that required checks were completed; they do not perform or prove those tests. Missing keys/contract/matching evidence means **do not activate**. Offline fixtures and blank model inference do not establish real matching readiness.

Run the non-uploading readiness check:

```powershell
& 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\VNZ-TECH\tools\activate_identity_dual.php' --check
```

This calls server health against the staged candidate configuration and makes no Face++ request. A failed check prints only a fixed stage name, never exception/provider/config contents. Run the existing cleanup-task validation as part of rollout; the v4 helper also requires a fresh successful cleanup heartbeat. No new Windows service or scheduled task is introduced.

## 4. Activate only after readiness passes

```powershell
& 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\VNZ-TECH\tools\activate_identity_dual.php' --activate
```

The CLI checks health first, writes the `identity_dual_required.flag` policy marker, then atomically replaces private config with `mode => auto_dual_v4`. Marker-first ordering means an interruption blocks verification rather than falling back to manual review. It preserves the encryption key, evidence, history and cleanup schedule. Remove neither required marker to bypass an outage. Policy reversion needs an owner decision.

Verify public/seller light/dark and mobile states, actual guest/account submission and carryover, and denied direct manual-review routes after rollout. Report source/tests, applied migration, private configuration, provider matching and physical-device results separately. A fresh camera capture from a phone over LAN needs HTTPS; viewport tests alone do not verify a physical phone.

## Implementation checks so far

`tests/identity_faceplusplus_test.php` uses synthetic JSON/JPEG fixtures and performs no network requests. It covers fixed region/TLS/bounds policy, absent/invalid configuration, strict threshold boundary, token/error filtering, malformed responses and HTTP/TLS/network/quota failures. Live provider contract, credential health, consented matching and physical-device coverage remain separate deployment prerequisites.
