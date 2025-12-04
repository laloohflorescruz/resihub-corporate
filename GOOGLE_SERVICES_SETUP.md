# Google Services Setup Instructions

## Overview
This document covers the setup for two Google services integrated into the ResiHub corporate website:
1. **Google Analytics 4 (GA4)** - Track visitor data, behavior, and conversions
2. **Google reCAPTCHA v3** - Prevent spam and bot submissions on forms

---

# Part 1: Google Analytics 4 Setup

## What is Google Analytics 4?
Google Analytics 4 (GA4) is the latest version of Google Analytics that provides insights about your website visitors, including:
- Number of visitors and page views
- User demographics (location, device, browser)
- User behavior (time on site, pages visited, navigation paths)
- Traffic sources (direct, organic search, referral, social)
- Conversion tracking (form submissions, button clicks)
- Real-time visitor monitoring

## Setup Steps for Google Analytics

### 1. Create a Google Analytics 4 Property

1. Go to [Google Analytics](https://analytics.google.com/)
2. Sign in with your Google account
3. Click **Admin** (gear icon in the bottom left)
4. Under **Account**, click **Create Account** (or select an existing account)
5. Fill in the account details:
   - **Account Name**: ResiHub
   - Check the data sharing settings as needed
6. Click **Next**
7. Create a property:
   - **Property Name**: ResiHub Corporate Site
   - **Reporting Time Zone**: Select your timezone
   - **Currency**: Select your currency
8. Click **Next**
9. Fill in business information and click **Create**
10. Accept the Terms of Service

### 2. Set Up a Data Stream

1. After creating the property, you'll be prompted to set up a data stream
2. Select **Web**
3. Enter your website details:
   - **Website URL**: https://resihub.crudcreativo.com
   - **Stream Name**: ResiHub Corporate Website
4. Click **Create Stream**
5. You'll see your **Measurement ID** (format: `G-XXXXXXXXXX`)
   - **Copy this ID** - you'll need it for the next step

### 3. Configure the Website Code

**Files to Update**: `index.html` and `features.html`

Find and replace `G-XXXXXXXXXX` with your actual **Measurement ID** in both files:

**In index.html (Lines ~125 and ~130)**:
```html
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-XXXXXXXXXX', {
        'send_page_view': true,
        'cookie_flags': 'SameSite=None;Secure'
    });
</script>
```

**In features.html (Lines ~73 and ~78)** - Same replacement

### 4. Verify Installation

1. Upload your updated files to the server
2. Visit your website
3. In Google Analytics, go to **Reports** → **Realtime**
4. You should see your visit showing up within 30 seconds
5. Check that pages are being tracked correctly

### 5. Set Up Key Events (Conversions)

Track important actions like form submissions:

1. In Google Analytics, go to **Admin** → **Events**
2. Click **Create Event**
3. Create custom events for:
   - Form submissions (`form_submit`)
   - Button clicks (`button_click`)
   - Demo requests (`demo_request`)

### 6. Useful Reports to Monitor

Once data starts collecting, check these reports:
- **Realtime**: See current visitors
- **Acquisition** → **Traffic Acquisition**: See where visitors come from
- **Engagement** → **Pages and Screens**: Most visited pages
- **User Attributes** → **Demographics**: Visitor location and device info
- **Events**: Track form submissions and button clicks

---

# Part 2: Google reCAPTCHA v3 Setup

## Overview
Google reCAPTCHA v3 has been integrated into all forms on the ResiHub corporate website to prevent spam and bot submissions. This is an invisible captcha that works in the background without requiring user interaction.

## Setup Steps

### 1. Get Your reCAPTCHA Keys

1. Go to [Google reCAPTCHA Admin Console](https://www.google.com/recaptcha/admin)
2. Click on the "+" button to register a new site
3. Fill in the registration form:
   - **Label**: ResiHub Corporate Site
   - **reCAPTCHA type**: Select "reCAPTCHA v3"
   - **Domains**: Add your domain(s):
     - resihub.crudcreativo.com
     - localhost (for testing)
     - Any other domains you'll use
4. Accept the terms and click "Submit"
5. You'll receive two keys:
   - **Site Key** (public key - used in HTML/JavaScript)
   - **Secret Key** (private key - used in PHP backend)

### 2. Configure the Frontend (JavaScript)

**File**: `index.html`

Find and replace `6LfYourSiteKeyHere` with your actual **Site Key** in two places:

1. **Line ~125** - In the reCAPTCHA script tag:
```html
<script src="https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY_HERE"></script>
```

2. **Line ~1669** - In the form submission handler:
```javascript
const token = await grecaptcha.execute('YOUR_SITE_KEY_HERE', {action: 'submit'});
```

### 3. Configure the Backend (PHP)

**File**: `send-email.php`

Find and replace `YOUR_SECRET_KEY_HERE` with your actual **Secret Key**:

**Line ~8**:
```php
$recaptcha_secret_key = "YOUR_SECRET_KEY_HERE";
```

### 4. Adjust Score Threshold (Optional)

reCAPTCHA v3 returns a score between 0.0 (likely a bot) and 1.0 (likely a human). The default threshold is 0.5.

In `send-email.php` (line ~9), you can adjust this:
```php
$recaptcha_score_threshold = 0.5; // Values: 0.0 - 1.0
```

Recommended values:
- **0.3** - More lenient (allows more submissions, may allow some spam)
- **0.5** - Balanced (recommended)
- **0.7** - Stricter (blocks more potential spam, may block some humans)

## How It Works

### User Experience
1. User fills out any form on the site
2. When they click submit, reCAPTCHA invisibly analyzes their behavior
3. A token is generated and sent with the form data
4. If verification passes, the form is submitted successfully
5. If verification fails, user sees an error message

### Forms Protected
The following forms are now protected:
1. **Contact Form** (`#contact-form`) - Main contact section
2. **Start Form** (`#start-form`) - "Comienza Ahora" modal
3. **Sales Form** (`#sales-form`) - Sales inquiry modal

## Testing

### Test in Development
1. Add `localhost` to your reCAPTCHA domains in Google Console
2. Test form submissions locally
3. Check browser console for any errors
4. Verify emails are being received

### Monitor in Production
1. Visit [reCAPTCHA Admin Console](https://www.google.com/recaptcha/admin)
2. Select your site
3. View analytics to see:
   - Number of requests
   - Score distribution
   - Suspicious activity

## Troubleshooting

### Common Issues

**Error: "Verification of security failed"**
- Check that the Site Key in HTML matches your reCAPTCHA site
- Ensure domain is added to reCAPTCHA allowed domains
- Check browser console for JavaScript errors

**Error: "Your request did not pass our security checks"**
- Score is below threshold
- Try lowering `$recaptcha_score_threshold` in PHP
- User may need to use a different browser or clear cookies

**Forms not submitting**
- Check browser console for errors
- Verify reCAPTCHA script is loading: `https://www.google.com/recaptcha/api.js`
- Ensure Secret Key is correct in `send-email.php`
- Check PHP error logs

### Debug Mode

To see the actual reCAPTCHA score for debugging, temporarily add this to `send-email.php` after line 66:

```php
error_log("reCAPTCHA Score: " . $recaptcha_response->score);
```

Then check your server error logs to see the scores users are getting.

## Security Notes

- **Never expose your Secret Key** - Keep it only in server-side code
- **Store keys in environment variables** in production (recommended):
  ```php
  $recaptcha_secret_key = getenv('RECAPTCHA_SECRET_KEY');
  ```
- **Use HTTPS** - reCAPTCHA requires secure connections in production
- **Monitor regularly** - Check analytics for unusual patterns

## Additional Resources

- [reCAPTCHA v3 Documentation](https://developers.google.com/recaptcha/docs/v3)
- [Score Interpretation Guide](https://developers.google.com/recaptcha/docs/v3#interpreting_the_score)
- [Admin Console](https://www.google.com/recaptcha/admin)

## Support

If you encounter issues:
1. Check the troubleshooting section above
2. Review Google's reCAPTCHA documentation
3. Check server and browser console logs
4. Verify all keys are correctly configured
