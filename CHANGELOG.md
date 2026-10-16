# Changelog

## Unreleased

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
