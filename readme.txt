=== WooCommerce Gravity Forms Product Add-ons ===
Contributors: lucasstark
Tags: woocommerce, gravity forms
Requires at least: 3.0
Tested up to: 3.3
Stable tag: 1.3

WooCommerce Gravity Forms.  This add-on requires Gravity Forms 1.6 or later.  


== Installation ==

1. Upload the folder 'woocommerce-gravityforms-product-addons' to the '/wp-content/plugins/' directory

2. Activate 'WooCommerce - Gravity Forms Product Add-Ons' through the 'Plugins' menu in WordPress

== Usage ==

1. Edit a product and look for the 'Gravity Forms' write panel.  The product must have a non-empty price, enter 0.00 if the product is free. 

2. Choose a gravity form that you would like to use as a product form for your product. 

3. Enter in any text before / after the standard price.  This allows you to add something such as Starts:  
for products that are not variable, but based on the gravity form options will have a variable price. 

4: Update / publish the product

5: Browse to the product from your store, fill out the gravity form, and validate that all the correct information is being collected 
throughout the process. 

-- Gravity Forms -- 
Build a gravity form using any standard form field you require. 

This add-on supports both standard data collection fields ( such as a text box ), and pricing fields that effect the price.  If you would like to use pricing fields on your form, build a Gravity Form with a Product Field and a Total field. Typically you will set the Product Field to be hidden and set the price to 0.  You would then use additional pricing fields to modify the price of the item as options are selected.    If you are building a donation style form, you can use the product field and allow a user defined price.  

If you are building a Gravity Form that includes pricing fields, you can add the css class of hidden-total to the total field on the gravity form to hide this line item.  WooCommerce-Gravity Forms includes a built in function that will display the total of the customizations selected on the gravity form when a user is adding the item to their cart. 

-- WooCommerce --
WooCommerce 1.4 or greater is required. 

Grouped products are not supported.

On the product admin screen you will see a Gravity Forms meta box.  This meta box allows you to choose the gravity form to be linked to the product as well as configure various display options for the form.