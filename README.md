# ZaioTV Pharmacy Rotations Plugin

A comprehensive, automated WordPress plugin designed to manage and display **On-Duty Pharmacy Schedules (صيدليات الحراسة)** for the city of **Zaio** and surrounding regions in **Morocco**.

Developed by **Seddik Belbikkey**.

---

## 🌟 Overview

**ZaioTV Pharmacy Rotations (صيدليات الحراسة)** is an automated, enterprise-grade WordPress plugin engineered to manage and publish **On-Duty Pharmacy Schedules (صيدليات الحراسة)** for local municipalities and news portals in Morocco (such as **ZaioTV.Net**).

### 💡 How On-Duty Pharmacy Rotations Work
In Moroccan cities like **Zaio**, pharmacies regularly operate during fixed standard business hours. However, **after regular hours, on weekends, and during public holidays**, only the designated **On-Duty Pharmacy (صيدلية الحراسة)** stays open—operating **24 hours a day (24h/24)** for its assigned rotation week.

This plugin automates the entire management lifecycle:
* **Automated 24h Shift Handover**: Accurately calculates the active on-duty pharmacy using `Africa/Casablanca` time rules and standard 09:00 AM shift transitions.
* **Theme-Independent Dual Templates**: Includes built-in, responsive **Desktop** (`templates/template-pharmacy.php`) and **Mobile** (`templates/mobile/template-pharmacy.php`) page templates that auto-route based on visitor device (`wp_is_mobile()`).
* **Live Interactive Navigation**: Features embedded interactive Google Maps, one-touch mobile phone dialing (`tel:`), direct GPS directions, and drag-and-drop admin rotation management.
* **REST API Readiness**: Exposes JSON REST API endpoints (`/wp-json/zaiotv/v1/hirassa`) for native Android and iOS app integrations.

---

## 🚀 Key Features

* ⏰ **Morocco Duty Time Engine**: Accurately calculates active duty rosters based on exact local time (`Africa/Casablanca`) and 09:00 AM shift transitions.
* 📱 **Native Mobile & Desktop Templates**: Self-contained page templates that auto-load without requiring theme modifications.
* 🗺️ **Embedded Google Maps**: Satellite/roadmap view with custom pharmacy pins and GPS navigation shortcuts.
* 📞 **One-Touch Mobile Dialing**: Direct `tel:` call action buttons optimized for smartphones.
* 🔄 **Drag-and-Drop Sequence Manager**: Effortlessly reorder pharmacy shift rotations inside the WP Admin dashboard.
* 🔌 **WordPress REST API Integration**: Public endpoints for mobile apps and third-party integrations.

---

## 🛠️ Installation & Usage

1. **Install Plugin**:
   * Download or clone this folder into your WordPress plugins directory: /wp-content/plugins/zaio-v14-pharmacy-rotations.
   * Activate **صيدليات الحراسة** from **Plugins > Installed Plugins** in the WordPress admin panel.

2. **Configuration**:
   * Navigate to **صيدليات الحراسة** in your WordPress admin menu.
   * Add local pharmacies with their details and assign active duty dates/times.

3. **Displaying Duty Schedule**:
   * Use shortcode [zaio_pharmacies] inside any post or page.
   * Or use PHP helper function zaio_v14_get_current_duty_pharmacies() inside your theme files.

---

## 👤 Author & Credits

* **Developer**: Seddik Belbikkey
* **Website**: [https://seddik.be](https://seddik.be)
* **demo**: [https://zaiotv.net](https://zaiotv.net/pharmacy)
