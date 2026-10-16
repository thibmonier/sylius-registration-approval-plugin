# Changelog

## 0.1.0 - 2026-10-16

### Added

- Registration approval for the channels listed in `tmonier_sylius_registration_approval.channels`:
  the account created by a registration of the shop (page, registration after checkout, shop API) is
  `pending` until an administrator approves or rejects it. Four additive columns on
  `sylius_customer` (trait `ApprovalTrait`, migration `Version20261016300000`); existing customers
  stay `approved`.
- A pending or rejected customer is anonymous for `tmonier/sylius-channel-access-plugin`
  (decorated resolver) and gets a 403 waiting page (JSON 403 for the shop API) where an anonymous
  visitor would be sent to the login.
- Admin: *Customers > Registrations* (filter on the status, default pending), approve / reject
  buttons (POST, CSRF token, administration role), optional internal reason, decision stored with
  date and author, a decision is never replayed.
- E-mails of approval and rejection (Sylius mailer, seven languages); a mailer failure is logged and
  never blocks the decision.
- No account enumeration on the registration page and the shop API of the validating channels.
- Public contract: `ApprovalStatus`, `RegistrationApprovalManagerInterface`, `ApprovalDecidedEvent`,
  `VisitorApprovalInterface`, `ApprovalPolicyInterface`.

### Security

- Every self-service registration is `pending` whatever the channel (was: only on validating
  channels), closing the register-elsewhere-then-sign-in bypass.
- The duplicate e-mail path hashes a throwaway password (no timing difference).
- `tmonier:registration-approval:check` and a registration-time warning for a validating channel left
  in `open` mode.

### Compatibility

- Requires Sylius 2.3 and PHP 8.3 (the hook `condition` option does not exist in Sylius 2.2).
