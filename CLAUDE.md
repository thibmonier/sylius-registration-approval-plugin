# CLAUDE.md

Sylius 2 plugin: an administrator approves or rejects the accounts registered on some channels.
Depends on `tmonier/sylius-channel-access-plugin` (its public contract), which turns pending and
rejected customers away. Research and decisions: `SPEC.md`. Public docs: `README.md`.

## Commands

PHP needs more than 128 MB to compile the test application container: prefix console and
PHPStan calls with `php -d memory_limit=2G` when running locally.

```bash
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # integration + functional, need the test DB + Sylius fixtures
vendor/bin/phpstan analyse                # level max, src + tests, no ignore
vendor/bin/ecs check
vendor/bin/rector process --dry-run
vendor/bin/console lint:container
```

Local env overrides (gitignored): `tests/TestApplication/.env.local` and `.env.test.local`
(DATABASE_URL). Database: `tmonier_sylius_registration_approval_test`, migrated and loaded with the
default fixture suite. The shop assets must be built (`vendor/sylius/test-application/public/build`).
After adding a `translations/` file or a route, run `cache:clear -e test`.

`composer.json` resolves the access plugin from the sibling directory (`path` repository,
`^0.1@dev`) until it is on Packagist; then require `^0.1` and drop the repository.

## Architecture

- `Entity/Customer/ApprovalAwareInterface` + `ApprovalTrait`: columns `approval_status` (default
  `approved`; an unknown value reads as `pending`), `approval_decided_at`, `approval_decided_by`,
  `approval_reason`. Test entity: `tests/TestApplication/src/Entity/Customer/Customer`.
- `Approval/`: `ApprovalStatus`, `ApprovalPolicyInterface` (configured channel codes),
  `RegistrationApprovalManager` (pessimistic lock, pending -> approved|rejected only, dispatches
  `ApprovalDecidedEvent` after the commit; throws `InvalidApprovalTransitionException` outside the
  transaction because an exception inside would close the entity manager), `VisitorApproval`
  (status of the signed-in customer when the channel validates and the customer is not approved),
  `RegistrationMarker` (Doctrine `prePersist` of a new `ShopUser`: shop and shop API sections only, ANY channel; warns when a validating channel is `open`). `Command/CheckConfigurationCommand` reports validating channels that restrict nothing.
- `Access/ApprovalAwareAccessModeResolver` decorates `tmonier_channel_access.resolver`: a pending or
  rejected customer is "not signed in" there, so all its protections apply (hidden prices, add to
  cart, API...). `EventListener/PendingCustomerListener` (kernel.request 7, before the access
  listener at 6) turns the refusal of the access plugin (`VisitorAccessListener::refusalFor()`) into
  a 403 waiting page (JSON 403 for `/api/v2/shop/*`).
- No account enumeration: `Form/Extension/RegistrationTypeExtension` removes the unique e-mail error
  of the registration page, `RegistrationListener::onPreRegister` (`sylius.customer.pre_register`)
  stops the creation and redirects to the same thank-you route, `onPostRegister` signs the new
  customer out and redirects to the same URL; `RegistrationReceivedListener` renders one "received"
  page for both. Shop API: `Validator/ApprovalAware...Validator` and `Api/ApprovalAware...Handler`
  decorate the core validator and handler (204 for both cases).
- `Mailer/ApprovalMailer` (event listener, failures logged only) with the Sylius sender, e-mails
  `tmonier_registration_approved|rejected` (`config/mailer.yaml`), locale = channel default locale.
- Admin: grid `tmonier_registration_approval_admin` on the customer resource (status filter,
  default `pending`), POST-only `DecisionController` (administration role, CSRF token
  `tmonier_registration_decision_<id>`), menu under Customers.
- Tests: `tests/RegistrationApprovalTestTrait` (`account()` creates an account in a given status
  OUTSIDE of any HTTP request, otherwise the marker would make it pending).
