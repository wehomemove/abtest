# Claude Prompt: Integrate A/B Testing Package into Motus

Copy and paste this prompt to Claude when working on your Motus project:

---

I need help integrating a custom Laravel A/B testing package into my Motus project. The package is located at `/Users/shaun/Documents/Homemove/abtest` and needs to be installed and configured in my Motus Laravel application.

## Package Details

**Package Name:** `homemove/ab-testing`  
**Package Location:** `/Users/shaun/Documents/Homemove/abtest`  
**Main Service:** `AbTestService` with facade `AbTest`  
**Key Features:** Variant assignment, event tracking, debug panel, automatic JavaScript injection

## Required Integration Steps

1. **Install the package** in my Motus project using Composer VCS repository configuration
2. **Run database migrations** to create the required tables (`ab_experiments`, `ab_user_assignments`, `ab_events`)
3. **Register the service provider** if auto-discovery doesn't work
4. **Create a test page** to verify the integration works
5. **Set up a test experiment** called `survey_red_buttons` with variants `control` and `red_buttons`

## Package API Endpoints

The package provides these API routes:
- `GET /api/ab-testing/variant/{experiment}` - Get variant for experiment
- `POST /api/ab-testing/variant` - Get variant with POST data
- `POST /api/ab-testing/track` - Track events
- `POST /api/ab-testing/register-debug` - Register debug experiments

## Expected Functionality

- **Variant Assignment:** Users should be assigned to either `control` or `red_buttons` variants
- **Event Tracking:** Button clicks and conversions should be tracked
- **Debug Panel:** When `APP_DEBUG=true`, a debug panel should appear in bottom-right corner
- **JavaScript Helper:** `window.abtrack()` function should be auto-injected on pages

## Test Files Available

I have these test files ready to copy into Motus:
- `motus-test-controller.php` - Controller for test page
- `motus-test-view.blade.php` - Blade template for test page  
- `motus-routes.php` - Routes to add to web.php
- `MOTUS_INTEGRATION_STEPS.md` - Detailed integration guide

## My Motus Project Structure

My Motus project is a standard Laravel application. Please help me:

1. **Install the package correctly** using the proper Composer configuration
2. **Copy and configure the test files** into the right locations
3. **Set up the database** with test data
4. **Verify everything works** by creating a working test page at `/ab-test`
5. **Troubleshoot any issues** that come up during integration

## Expected Test Results

After integration, I should be able to:
- Visit `https://motus.localhost/ab-test` and see a test page
- Click "Check Package Status" and get `"success": true`
- See either a blue button (control) or red button (red_buttons variant)
- Track events successfully
- See the debug panel when debug mode is enabled

Please guide me through this integration step by step, checking each step works before moving to the next one.

---

**This prompt gives Claude all the context needed to help you integrate the package into Motus successfully!** 🚀