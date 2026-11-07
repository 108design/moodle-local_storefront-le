<p align="center">
  <img src="docs/branding/logo.svg" alt="Storefront logo" width="125" height="125">
</p>

# Storefront LE

Sell access to Moodle courses through a searchable catalogue and shopping cart. A product can include several courses, and courses can belong to several products. Each offer is a one-time purchase for the signed-in Moodle account.

<!--
## Screenshots

<details>
<summary>View screenshots</summary>

</details>
-->

## Installation

Supports Moodle 4.5 through 5.3. Download the [signed beta ZIP](https://github.com/108design/moodle-local_storefront-le/releases) and install it as `local/storefront`. For course sales, install and enable [Storefront Enrolment](https://github.com/108design/moodle-enrol_storefront), map each product to its courses and configure a Moodle payment account with a gateway that supports the offer currency. Check your gateway in its sandbox before accepting payments.

Manage products, offers, categories, tags, orders, legal texts and transactional messages from the Storefront administration pages. Course mappings determine the role and access duration. Timed access starts when the course access is delivered. Several active purchases preserve access until the latest applicable end date; purchased durations are not added together.

Keep Moodle cron running. It retries incomplete course delivery after a verified payment and processes queued transactional messages.

Buyers can return to pending payments and see delivery status and course access under My Purchases. Administrators can retry failed course delivery for a verified payment. A gateway return page does not confirm payment. Refund and dispute handling requires a procedure appropriate to the chosen gateway.

Publish up to two legal documents, each with language versions and revisions. Checkout records the versions displayed and any required confirmations. Existing order evidence is retained when a text changes.

Optional Magic Link sign-in uses the installed Moodle authentication plugin and its registration policy. Selection resumes in the cart, including on another browser. Moodle login and any required account or MFA steps remain in effect.

## Managing course access

Open **Orders and Access** to suspend or restore a purchased course access. The action applies to the selected purchase and keeps its original expiry. Handle refunds and disputes through your payment provider.

## License

This release is available free of charge under the 108design Software License.
See [LICENSE.md](LICENSE.md) for the complete terms.
