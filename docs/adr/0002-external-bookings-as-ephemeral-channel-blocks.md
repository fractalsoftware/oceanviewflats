# External Bookings Treated Exclusively as Ephemeral Channel Blocks

External Online Travel Agency bookings (e.g. from Airbnb) are ingested purely as ephemeral calendar date blocks (`Channel Block`) cached on disk and checked in-memory during availability validation. Background sync feeds never write directly to the MySQL `reservations` table. This keeps the database as an authoritative ledger strictly for direct guests and explicitly onboarded stays, avoiding synthetic records and complex bidirectional two-way sync state mutations. Per [ADR 0007](0007-external-platform-reservations-and-guest-onboarding.md), an Admin User may explicitly onboard a Channel Block into an `External Reservation` (`res-abnb-*`), which absorbs the matching Channel Block on the Reservation Ledger.

