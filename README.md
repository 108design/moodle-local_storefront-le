# Storefront

Sell access to Moodle courses through a searchable catalogue and shopping cart. A product can include several courses, and courses can belong to several products. Each offer is a one-time purchase for the signed-in Moodle account.

Install Storefront and Storefront Enrolment on Moodle 4.5 or later. Enable the Storefront enrolment method, map each product to its courses, and configure an enabled Moodle payment account with a gateway supporting the offer currency. Check your chosen gateway in its sandbox before accepting payments.

Manage products, offers, categories, tags, orders, legal texts and transactional messages from the Storefront administration pages. Course mappings determine the role and access duration. Timed access starts when the course access is delivered. Several active purchases preserve access until the latest applicable end date; purchased durations are not added together.

Keep Moodle cron running. It retries incomplete course delivery after a verified payment and processes queued transactional messages.

Buyers can return to pending payments and see delivery status and course access under My Purchases. Administrators can retry failed course delivery for a verified payment. A gateway return page does not confirm payment. Refund and dispute handling requires a procedure appropriate to the chosen gateway.

Publish up to two legal documents, each with language versions and revisions. Checkout records the versions displayed and any required confirmations. Existing order evidence is retained when a text changes.

Optional Magic Link sign-in uses the installed Moodle authentication plugin and its registration policy. Selection resumes in the cart, including on another browser. Moodle login and any required account or MFA steps remain in effect.

## License

This release is available free of charge under the 108design Software License.
See [LICENSE.md](LICENSE.md) for the complete terms.
