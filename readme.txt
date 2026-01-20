=== eCommerce Wunderkiste ===
Contributors: michaelkanda
Donate link: https://designare.at
Tags: woocommerce, pricing, shipping, accessories, image
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extended product options: Price on Request, Shipping per Product, Accessories, Image Resizer & Tiered Pricing.

== Description ==

eCommerce Wunderkiste adds powerful product management features to your WooCommerce store. All modules can be enabled/disabled individually.

= Features =

**Price on Request**
* Hide product prices and display "Price on Request" instead
* Remove the "Add to Cart" button
* Customizable display text per product
* Custom CSS for individual styling

**Disable Shipping Methods**
* Disable specific shipping methods per product
* Shows all configured shipping zones and methods
* When a product is in the cart, disabled shipping methods are hidden

**Accessories Tab (Cross-Selling)**
* Adds a new "Accessories" tab on the product page
* Easy product assignment via search in the product sidebar
* Ideal for spare parts or complementary items

**Image Resizer 800px / 1200px**
* Resize images to max 800px or 1200px width/height
* Button in media library (single and list view)
* High quality (92%) for optimal results
* Overwrites original image and updates all metadata

**Order Recovery (Payment Failure)**
* Helps recover lost sales from abandoned payments
* Scenario A: Automatically sends an email (with payment link) when an order has "Payment Pending" status for 1 hour
* Scenario B: Immediately sends an email when a payment fails
* Scenario C: Adds a button in the order overview to manually resend the payment link via email

**Tiered Pricing**
* Enables quantity-based prices (volume discounts)
* Automatic price adjustment in the cart when quantity threshold is reached
* Automatically displays a price table on the product page
* Flexibly configurable (e.g., 1-5 pieces: €4, 6+ pieces: €3)

== Installation ==

1. Upload the `ecommerce-wunderkiste` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **WooCommerce → Product Extras** to enable the desired modules

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes, WooCommerce must be installed and activated for this plugin to work.

= Can I enable only specific modules? =

Yes, all modules can be enabled or disabled individually in the settings under WooCommerce → Product Extras.

= Where do I configure product-specific settings? =

After enabling modules, product-specific settings appear in the product editor sidebar (for Price on Request, Shipping, and Accessories) and below the editor (for Tiered Pricing).

= Is the Image Resizer destructive? =

Yes, the Image Resizer overwrites the original image. Make sure to keep backups of your original images if needed.

== Screenshots ==

1. Settings page with module toggles
2. Price on Request settings in product sidebar
3. Disable Shipping Methods in product sidebar
4. Accessories selection in product sidebar
5. Tiered Pricing configuration
6. Image Resizer buttons in media library

== Changelog ==

= 1.1.0 =
* Added Tiered Pricing module
* Added Order Recovery module
* Improved security with proper input sanitization
* Fixed text domain for internationalization
* Code quality improvements

= 1.0.0 =
* Initial release
* Price on Request module
* Disable Shipping Methods module
* Accessories Tab module
* Image Resizer module

== Upgrade Notice ==

= 1.1.0 =
New features: Tiered Pricing and Order Recovery modules. Improved security and code quality.

== CSS Customization ==

You can customize the appearance using these CSS classes:

* `.price-on-request` - The "Price on Request" text
* `.wpe-tiered-pricing-table` - The tiered pricing table in frontend

Example:
`.price-on-request { color: #e74c3c; font-weight: bold; }`
