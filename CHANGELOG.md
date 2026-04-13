# Changelog

All notable changes to this project will be documented in this file.

The format is inspired by Keep a Changelog and this project follows Semantic Versioning.

## [Unreleased]

### Planned
- Charter integration bridge package (`charter-midtrans`) on top of PayID + Midtrans SDK.
- Additional docs for deployment and migration examples.

## [0.1.0] - 2026-04-13

### Added
- Initial Midtrans driver integration for PayID with capability-based operations.
- Snap charge flow mapping to PayID `ChargeResponse`.
- Core API direct charge mapping (VA/QRIS/GoPay and related payload handling).
- Transaction lifecycle operations: status, cancel, expire, approve, deny, refund.
- Subscription lifecycle operations: create, get, update, pause, resume, cancel.
- GoPay account linking operations: link, get, unlink.
- Webhook verification and parsing pipeline with replay protection.
- Driver service provider registration and package bootstrap wiring.

### Changed
- Refactored HTTP integration to use shared `aliziodev/midtrans-php` SDK as the Midtrans transport and endpoint wrapper foundation.
- Mapped SDK exceptions into PayID exception contracts for consistent app-level error handling.
- Normalized dependency constraints for released PayID core (`^0.1`).

### Fixed
- Endpoint compatibility updates for Midtrans Subscription v1 lifecycle semantics.
- Composer lock/config synchronization and release-readiness validation.

### Quality
- Test suite passing (`76` tests, `207` assertions).
- Static analysis passing (`phpstan`).
- Composer validation passing (`composer validate --strict`).
