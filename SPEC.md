# Specification — registration approval plugin

US-020 of the B2B demo (sprint 7). Standalone plugin; depends on the public contract of
`tmonier/sylius-channel-access-plugin` (US-019). Checked against Sylius 2.3.1, twig-hooks 0.14.1,
Symfony 7.4.

## 1. Threat model (what the security review should attack)

Assets: the closed catalogue and the prices; the decision itself; the knowledge of who has an account.

| # | Attack | Defence |
|---|---|---|
| 1 | Use a pending / rejected account as an approved one | The status is read from the database at every request (the user is refreshed by the provider). `ApprovalAwareAccessModeResolver` decorates the access plugin resolver, so every protection of that plugin treats the customer as anonymous. An unknown stored status reads as `pending` |
| 2 | Bypass through another entry point (API token, live component, add to cart) | JWT users go through the same resolver; `PriceGuardOrderModifier` of the access plugin uses the decorated resolver; tests cover the shop API (403), the add to cart and pages |
| 3 | Forge a decision (CSRF, non-admin, anonymous, customer session) | `DecisionController`: POST only, `ROLE_ADMINISTRATION_ACCESS` and CSRF token `tmonier_registration_decision_<id>` checked before any lookup; the admin firewall also refuses; tests: no token, forged, other id, limited admin, anonymous, customer |
| 4 | Replay / race two decisions | Pessimistic lock + `refresh` in a transaction, only `pending` can be decided; the refusal is thrown after the commit |
| 5 | Account enumeration on the registration page | The unique e-mail form error is removed on validating channels; creation is stopped in `sylius.customer.pre_register`; both paths redirect to the same URL and render the same page; the new customer is signed out; tests compare the final pages and the mixed-error case |
| 6 | Account enumeration through the shop API | The core `UniqueShopUserEmail` validator and the register handler are decorated: 204 for both. Other violations stay and are identical |
| 7 | Guest customer re-registers to skip the validation | The marker works on the new `ShopUser` (prePersist), not on the customer: an existing guest customer becomes pending when it gets an account |
| 8 | Admin-created accounts or fixtures blocked | The marker only acts for the shop and shop API sections with an HTTP request |
| 9 | Stored XSS through the internal reason | Never rendered; limited to 500 characters; Twig autoescapes the status column (decider name) |
| 10 | E-mail abuse / secrets | The mail carries a fixed text, no reason, no token; sent only by the decision; failures are logged and swallowed |

Accepted limits are listed in the README.

## 2. Design decisions

- **Status on the customer**, not on the user: the registration of a guest customer keeps its history.
  Latest decision only (status, date, author, reason): no history table (YAGNI); the event allows an
  external audit.
- **Channels by configuration** (codes), not by an admin flag: the sprint story only asks for
  a channel list; a policy interface allows a flag later.
- **403 waiting page, not a redirect** for the signed-in pending customer: the access plugin only
  knows how to redirect anonymous visitors; `VisitorAccessListener::refusalFor()` (public method of
  the access plugin) tells whether an anonymous visitor would be refused and the plugin answers 403.
- **New customers are signed out after registering** on validating channels (Sylius would sign them
  in): needed to make the duplicate e-mail answer identical, and pointless otherwise.
- **Takes effect on the next request**, not "at next login": the status is read per request.
- **Dependency on a class method** (`refusalFor`) and on the service ids
  `tmonier_channel_access.resolver` and `tmonier_channel_access.listener.visitor_access`: documented in
  the access plugin as extension points.

## 3. Test strategy

Unit: trait, policy, visitor approval and resolver decorator matrices, marker, listener, mailer,
API handler, validator, configuration. Integration: manager (transitions, lock, events), columns,
add to cart of a pending customer. Functional: registration (new / existing / mixed errors / API /
guest), pending access (closed, prices hidden, open, API), administration (list, approve, reject,
replay, CSRF, roles, e-mails in two locales).
