# Sylius Registration Approval Plugin

[![Build](https://github.com/thibmonier/sylius-registration-approval-plugin/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-registration-approval-plugin/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Open a B2B catalogue only to real customers: accounts registered on some channels wait for an
administrator to approve or reject them.**

Built on [`tmonier/sylius-channel-access-plugin`](https://github.com/thibmonier/sylius-channel-access-plugin):
that plugin closes a channel (or hides its prices) for visitors who are not signed in; this one
makes a customer whose registration is not approved count as such a visitor.

## Features

- **Pending by default, on every channel.** The account created by a self-service registration
  (page, registration after checkout, shop API) is `pending` **whatever the channel it is made on**,
  so that registering on a channel that does not validate cannot be a way round the ones that do.
  The restriction itself only applies on the channels you list. Accounts created in the
  administration, by fixtures or by console stay approved, and so do all existing customers
  (`approved` comes from a decision of an administrator or from the migration default).
- **No access until approved.** A pending or rejected customer is "not signed in" for the access
  plugin: on a closed channel the catalogue answers **403** with a waiting page (JSON 403 on
  `/api/v2/shop/*`); on a prices hidden channel they see no price and cannot add to a cart; on an
  open channel nothing changes. They still reach the home page, the login and the logout.
- **Decision in the administration.** *Customers > Registrations* lists the pending registrations
  (filter on the status, default *pending*). *Approve* / *Reject* are POST forms with a CSRF token
  and need the administration role; an optional internal reason (500 characters) is stored with the
  date and the author. A decision is never replayed (a second click, or a second administrator, gets
  a warning and nothing changes). Takes effect on the customer's next request.
- **E-mails.** The customer is told of the approval or the rejection (Sylius mailer, in the default
  locale of the channel, seven languages). The internal reason is not sent. A mailer failure is
  logged and never blocks the decision.
- **No account enumeration.** On a validating channel, registering with an address that already has
  an account creates nothing and gets exactly the same answer as a new registration (same redirect,
  same page, same message, nobody signed in), on the registration page and on `POST
  /api/v2/shop/customers` (204 in both cases). The other validation errors are still shown, the same
  way for both.

## Requirements

| | Version |
|---|---|
| PHP | 8.3+ |
| Sylius | 2.3 (required by the access plugin) |
| `tmonier/sylius-channel-access-plugin` | ^0.1 |

## Installation

1. Install and configure the access plugin first (its README), then:

    ```bash
    composer require tmonier/sylius-registration-approval-plugin
    ```

2. Register the bundle in `config/bundles.php` (after the access plugin):

    ```php
    Tmonier\SyliusRegistrationApprovalPlugin\TmonierSyliusRegistrationApprovalPlugin::class => ['all' => true],
    ```

3. Add the trait to the customer entity of your application:

    ```php
    use Doctrine\ORM\Mapping as ORM;
    use Sylius\Component\Core\Model\Customer as BaseCustomer;
    use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalAwareInterface;
    use Tmonier\SyliusRegistrationApprovalPlugin\Entity\Customer\ApprovalTrait;

    #[ORM\Entity]
    #[ORM\Table(name: 'sylius_customer')]
    class Customer extends BaseCustomer implements ApprovalAwareInterface
    {
        use ApprovalTrait;
    }
    ```

   and declare it (`config/packages/_sylius.yaml`): `sylius_customer.resources.customer.classes.model`.
   A customer class without the trait is never restricted.

4. Import the configuration (grid, hooks, e-mails) in `config/packages/_sylius.yaml`:

    ```yaml
    imports:
        - { resource: "@TmonierSyliusRegistrationApprovalPlugin/config/config.yaml" }
    ```

5. Import the routes (waiting page, administration) in `config/routes.yaml`:

    ```yaml
    tmonier_sylius_registration_approval:
        resource: "@TmonierSyliusRegistrationApprovalPlugin/config/routes.yaml"
    ```

6. Choose the channels (`config/packages/tmonier_sylius_registration_approval.yaml`):

    ```yaml
    tmonier_sylius_registration_approval:
        channels: ['B2B_WEB']   # empty by default: the plugin changes nothing
    ```

7. Run the migration (four additive columns on `sylius_customer`, existing customers stay approved):

    ```bash
    bin/console doctrine:migrations:migrate
    ```

The waiting page route is added to the exemptions of the access plugin automatically.

## Using it

Set the channel to *closed* (or *prices hidden*) in the administration (access plugin), list it in
`channels`, and registrations wait for you in *Customers > Registrations*.

A listed channel whose access mode is *open* restricts nobody: pending customers browse and buy as
before. The plugin logs a warning at registration and `bin/console tmonier:registration-approval:check`
reports it (exit code 1; unknown channel codes too): run it in your deployment checks.

## Contract for other code

- `ApprovalStatus` (`pending`, `approved`, `rejected`: stored values, never renamed).
- `RegistrationApprovalManagerInterface::approve|reject(CustomerInterface, string $decidedBy,
  ?string $reason = null)`: throws `InvalidApprovalTransitionException` unless the customer is
  pending. Dispatches `ApprovalDecidedEvent` (customer, status, author, reason) after the commit:
  listen to it to notify a CRM.
- `VisitorApprovalInterface::restrictedStatus()`: the status of the signed-in customer when this
  channel validates registrations and the customer is not approved, else null.
- `ApprovalPolicyInterface::requiresApproval(ChannelInterface)`: replace the configured list
  (e.g. a flag on the channel) by decorating `tmonier_registration_approval.policy`.

## Limits

- The registration channel is not stored: the e-mails use the current channel when it validates
  registrations, else the first configured channel, in its default locale.
- An unknown URL on a closed channel redirects to the login even for a signed-in customer (the
  firewall has not run when the router rejects the path; see the access plugin).
- There is no *reopen* action: a rejected customer stays rejected (change the column by hand).
- Guest checkout customers (no account) are not pending; they cannot buy on a restricted channel
  anyway. A customer who registers later with the same address becomes pending.
- The registration page does the same work for a new and an existing address (the password is hashed
  in both cases, so the response time does not tell), but the e-mails still differ: a new
  registration may send the Sylius welcome or verification e-mail, the existing address receives
  nothing.
- `approval_decided_by` (the administrator's username) is **personal data**: include it in your GDPR
  records and erase or anonymise it with the administrator's account.
- Registrations made through an **asynchronous Messenger bus** (a handler running without an HTTP
  request) are not marked pending: the marker needs the shop request.

## Testing

See `CLAUDE.md`. The functional tests drive the real shop, API and administration: registration,
waiting page, decisions, CSRF and role checks, e-mails, no enumeration.

## License

MIT, see [LICENSE](LICENSE).
