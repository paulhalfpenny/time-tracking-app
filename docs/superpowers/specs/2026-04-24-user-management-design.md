# User Management Screen Design

## Goal

Replace the cramped inline-row editing on the Admin → Users screen with a clean modal-based edit experience, and simplify the list view to show only the columns needed for scanning user roles and status.

## Architecture

The existing `app/Livewire/Admin/Users/Index.php` component is modified in-place. No new routes or components are needed. The modal is driven by Livewire component state (`$editingId`) and rendered conditionally in the Blade view. Alpine.js (already present) handles focus-trap and keyboard dismissal (`@keydown.escape`).

## Role System

Three roles, unchanged:

| Role | Timesheet | Reports | Admin screens |
|---|---|---|---|
| Admin | ✓ | ✓ | ✓ |
| Manager | ✓ | ✓ | ✗ |
| User | ✓ | ✗ | ✗ |

Role labels stay as-is (`Admin`, `Manager`, `User`). The modal shows a one-line hint below the role dropdown describing what the selected role grants, updated dynamically via `wire:model`.

Role hints:
- **Admin** — Full access: timesheet, reports, and admin screens.
- **Manager** — Can view reports. Cannot access admin screens.
- **User** — Timesheet access only.

## List View

Columns: **Name**, **Email**, **Role** (coloured badge), **Active** (green/grey dot), **Edit** button.

Inactive users are displayed at reduced opacity (0.5) so they don't distract from active users. All other detail (job title, rate, capacity, contractor) is accessible only via the modal.

## Edit Modal

Opens when Edit is clicked. Closes on Save, Cancel, or Escape key. The page behind is not scrollable while the modal is open.

Fields:
- **Name** — read-only (sourced from Google OAuth)
- **Email** — read-only (sourced from Google OAuth)
- **Role** — editable dropdown; dynamic hint line below shows access description
- **Job Title** — free text, optional
- **Rate (£/hr)** — numeric, optional
- **Capacity (hrs/week)** — numeric, required, 0–168
- **Active** — checkbox
- **Contractor** — checkbox

Validation mirrors the existing component: role must be a valid enum value, capacity 0–168, rate non-negative if provided.

## Safety Guard

An admin cannot change their own role or mark themselves inactive. If the user being edited is the currently authenticated user and their role is `admin`, the Role dropdown and Active checkbox are disabled with a tooltip: "You cannot change your own role or deactivate yourself."

This is enforced in the Livewire `save()` method (server-side), not only in the UI.

## Implementation Scope

**Files to modify:**
- `app/Livewire/Admin/Users/Index.php` — modal visibility driven by `$editingId !== null` (no new property needed); add self-edit safety guard in `save()`
- `resources/views/livewire/admin/users/index.blade.php` — replace inline edit row with modal overlay; simplify table to 5 columns

**No new files, routes, migrations, or models required.**

## Testing

- Admin can open modal, edit all fields, save successfully
- Admin cannot save with invalid capacity (out of range) or invalid role
- Admin cannot change their own role or deactivate themselves (both UI disabled and server rejects)
- Inactive users appear faded in the list
- Modal closes on Escape, Cancel, and after successful Save
- Non-admin users cannot access `/admin/users` (existing gate, no change)
