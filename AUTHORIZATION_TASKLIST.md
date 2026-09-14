# Events Authorization Implementation - Task List

## Project: Add Authorization Checks to RA Events Component
**Repository:** Ramblers-Tools/ra-events  
**Branch:** Events-authorisation  
**Status:** In Progress  
**Last Updated:** 14 September 2026

---

## Completed Tasks ✅

### 1. Create AuthorizationHelper.php
- **File:** `com_ra_events/site/src/Helpers/AuthorizationHelper.php`
- **Status:** ✅ COMPLETE
- **Details:** 
  - Authorization logic with methods to check user login, event publication, event date validity
  - Authorization checks: superuser, com_ra_events group member, or event organizer
  - Helper methods: `checkViewEventAuthorization()`, `isAuthorizedUser()`, `isGroupMember()`, `isEventOrganizer()`, `canEditEvent()`, `canCreateEvent()`
  - Organizer identified via: `event.contact_id` → `contact_details.user_id`

### 2. Update EventModel.php
- **File:** `com_ra_events/site/src/Model/EventModel.php`
- **Status:** ✅ COMPLETE
- **Changes:**
  - Added `use` statement for `AuthorizationHelper`
  - Added protected property: `$authorizationHelper`
  - Added authorization check in `getItem()` method (lines 115-119)
  - Calls `$this->authorizationHelper->checkViewEventAuthorization($this->_item);`

### 3. Update Events List Template
- **File:** `com_ra_events/site/tmpl/events/default.php`
- **Status:** ✅ COMPLETE
- **Changes:**
  - Added `use` statement for `AuthorizationHelper`
  - Initialize `$authHelper = new AuthorizationHelper();` (line ~52)
  - Check `$canCreateEvent = $authHelper->canCreateEvent();` (line ~53)
  - Added Edit icon on each event line (conditional on `canEditEvent()`)
  - Added "New Event" button at foot of page (conditional on `canCreateEvent()`)

### 4. Update Event HtmlView
- **File:** `com_ra_events/site/src/View/Event/HtmlView.php`
- **Status:** ⏳ PENDING EXECUTION
- **Changes to make:**
  - Add `use` statements for `AuthorizationHelper`
  - Add protected properties: `$authorizationHelper`, `$canEdit`, `$canCreateEvent`
  - Initialize `$this->authorizationHelper = new AuthorizationHelper;` in `display()` method
  - Set `$this->canEdit` flag checking both core.create permission AND `canEditEvent()`
  - Set `$this->canCreateEvent` flag from `canCreateEvent()`

---

## Remaining Tasks 🚧

### 5. Update Event View Template (NEXT)
- **File:** `com_ra_events/site/tmpl/event/default.php`
- **Required Changes:**
  - Display event details using same functionality as Admin view
  - Show buttons conditionally based on authorization flags from HtmlView
  - Show Edit button if `$this->canEdit` is true
  - Show New Event button if `$this->canCreateEvent` is true
  - Display booking reports if user has permission and bookings exist

### 6. Testing & Validation
- **Actions required:**
  - Test unauthorized user access (should receive 403 error)
  - Test past event access (should receive 403 error)
  - Test unpublished event access (should receive 403 error)
  - Test organizer can view their own events
  - Test com_ra_events group member can view all events
  - Test superuser can view all events
  - Verify Edit icons appear only for authorized users
  - Verify New button appears only for authorized users

---

## Authorization Logic Summary

### View Event Authorization Rules:
1. **User must be logged in** (id != 0)
2. **Event must be published** (state = 1)
3. **Event must not be in the past** (event_date >= today)
4. **User must have authorization** via ANY of:
   - Superuser status (isSuperuser())
   - Member of `com_ra_events` security group
   - Event organizer (via contact_id → contact_details.user_id)

### Edit Permissions:
- User has `core.create` permission for com_ra_events AND
- Either in `com_ra_events` group (edit all events) OR is the organizer (edit own event)

### Create Permissions:
- User has `core.create` permission for com_ra_events

---

## Key Files Modified

| File | Type | Status |
|------|------|--------|
| com_ra_events/site/src/Helpers/AuthorizationHelper.php | NEW | ✅ Complete |
| com_ra_events/site/src/Model/EventModel.php | UPDATED | ✅ Complete |
| com_ra_events/site/tmpl/events/default.php | UPDATED | ✅ Complete |
| com_ra_events/site/src/View/Event/HtmlView.php | UPDATED | ⏳ Pending |
| com_ra_events/site/tmpl/event/default.php | UPDATED | 🔵 To Do |

---

## Important Notes

- **Organizer identification:** Uses `contact_id` field in events table, which links to `contact_details.id`, which has `user_id` field pointing to Joomla users
- **Authorization checks performed in:** Model layer (EventModel.php getItem()) for security
- **UI conditional display in:** View layer (HtmlView.php) and templates
- **No changes to Admin views** - authorization only applies to Site (front-end) views

---

## How to Continue in New Session

If continuing in a new chat session, provide this task list and reference:
- The current branch: `Events-authorisation`
- Current status: Steps 1-4 complete, Step 5-6 pending
- Focus on: Completing the Event view template update and testing
