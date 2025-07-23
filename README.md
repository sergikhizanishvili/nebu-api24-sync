# Nebu API24 Sync
The project represents the WordPress / WooCommerce plugin for Nebu, which does synchronization with API24.

## Product Import Workflow

### 1. Configuration
- Configure the plugin settings in **WooCommerce > Nebu API24**
- Set API Base URL, Token, and Merchant ID

### 2. Category Import
- Click **"Sync Categories from API24"** to import product categories
- Categories are imported with their hierarchy and stored with API24 IDs

### 3. Product Data Import  
- Click **"Sync Products from API24"** to import product data to custom table
- This imports all product information but doesn't create WooCommerce products yet

### 4. WooCommerce Product Creation
- Click **"Create WooCommerce Products"** to convert imported data into actual WooCommerce products

## Barcode Logic

### Simple Products
- Products with barcodes like `12345` (without `_1`, `_2`, `_3` suffixes) are created as simple products

### Variable Products  
- Products with barcodes like `12345_1`, `12345_2`, `12345_3` are created as variable products
- The base barcode (`12345`) becomes the main product
- Each suffixed barcode becomes a variation
- Original barcodes are stored as meta data for tracking

## Features

- **Category Hierarchy**: Automatically assigns products to categories and all parent categories
- **Product Attributes**: Converts API24 attributes to WooCommerce attributes  
- **Image Import**: Downloads and attaches main and gallery images
- **Price Management**: Handles regular price, sale price, and B2B pricing
- **Stock Management**: Syncs stock quantities
- **Variation Support**: Creates variable products with proper attributes
- **Meta Data**: Stores original API24 data for reference

## Technical Details

### Database Structure
- Custom table `wp_api24_products` stores imported product data
- WordPress categories use `category_api24_id` custom field for mapping
- WooCommerce products use various meta fields for API24 data tracking

### Key Classes
- `API24`: Handles API communication and data processing
- `DB`: Manages custom table operations
- `Utils`: Provides utility functions for categories and barcodes
- `Admin_Settings`: Provides admin interface for sync operations
