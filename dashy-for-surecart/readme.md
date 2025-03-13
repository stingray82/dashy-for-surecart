
### Navigation Customization

**What Stays the Same:**

-   **Filter Name:**  
    The filter hook used in the template (e.g., `apply_filters( 'myplugin_surecart_navigation', ... )`) must match exactly with the filter name used when you add your custom function via `add_filter()`.  
    **Example:**

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
// In your dashboard template:
$data['navigation'] = apply_filters( 'rup_sc_dashextender_surecart_navigation', $data['navigation'], $data, $controller );
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

This needs to be consistent to hook into the filters 

**Hooking in**

 

**Parameters Order and Count:**  
When you hook into this filter, ensure that the number and order of parameters match the original declaration. In this case, the parameters are the navigation array, the data array, and the controller object.

  
**Example:**

    add_filter( 'rup_sc_dashextender_surecart_navigation', 'my_custom_dashboard_navigation', 10, 3 );
    function my_custom_dashboard_navigation( $navigation, $data, $controller ) {
        // Add your custom tab.
        $navigation['custom_tab'] = [
            'name'      => __( 'Custom Tab', 'my-surecart-dashboard' ),
            'icon_name' => 'custom-icon',
            'href'      => add_query_arg( 'sc-page', 'custom_tab', get_permalink() ),
        ];
        return $navigation;
    }
**Navigation Array Structure:**  
The custom tab you add should have specific keys:

-   `name`: The display name of the tab.
-   `icon_name`: The identifier for the icon.
-   `href`: The URL (often including a query parameter) that points to your custom content.

These keys are expected by the dashboard template for consistent rendering.

### Loading Custom Content

The dashboard template checks the URL for the query parameter (e.g., `?sc-page=custom_tab`) and loads content accordingly. You have three common options to render this content:

#### Option 1: Custom HTML Output

-   **Action Hook Name:**  
    You need to use the same action hook (e.g., `rup_sc_dashextender_surecart_dashboard_right`) provided by the dashboard template.
    
-   **Direct Output:**  
    Your function directly echoes HTML content.
    
    **Example:**

```
	add_action( 'rup_sc_dashextender_surecart_dashboard_right', 'my_custom_dashboard_tab_content_html', 10, 2 );
function my_custom_dashboard_tab_content_html( $controller, $data ) {
    echo '<div class="my-custom-tab-content">';
    echo '<h2>' . __( 'Custom HTML Content', 'my-surecart-dashboard' ) . '</h2>';
    echo '<p>This is hard-coded content for the custom tab.</p>';
    echo '</div>';
}
```

 #### Option 2: Loading a WordPress Page

-   **Page Slug:**  
    A page with a specific slug (e.g., `dashboard-content`) should exist. The function retrieves this page and processes its content.
    
-   **Consistency in Slug:**  
    The slug used in `get_page_by_path( 'dashboard-content' )` must match the actual page slug.
    
    **Example:**
```
add_action( 'rup_sc_dashextender_surecart_dashboard_right', 'my_custom_dashboard_tab_content_page', 10, 2 );
function my_custom_dashboard_tab_content_page( $controller, $data ) {
    $page = get_page_by_path( 'dashboard-content' );
    if ( $page ) {
        $content = apply_filters( 'the_content', $page->post_content );
        echo '<div class="my-custom-tab-content">';
        echo $content;
        echo '</div>';
    } else {
        echo '<div class="my-custom-tab-content">';
        echo '<h2>' . __( 'Content Not Found', 'my-surecart-dashboard' ) . '</h2>';
        echo '<p>Please create a page with the slug "dashboard-content".</p>';
        echo '</div>';
    }
}
```


#### Option 3: Rendering a Shortcode (prefered & easiest option)

-   **Shortcode Usage:**  
    You can render content through a shortcode using `do_shortcode()`. Make sure the shortcode exists and is properly registered.
    
    **Example:**

Example:
```
add_action( 'rup_sc_dashextender_surecart_dashboard_right', 'my_custom_dashboard_tab_content_shortcode', 10, 2 );
function my_custom_dashboard_tab_content_shortcode( $controller, $data ) {
    echo '<div class="my-custom-tab-content">';
    echo do_shortcode( '[my_shortcode]' );
    echo '</div>';
}

```

### Key Points to understand

1.  **Consistency is Crucial:**
    
    -   **Filter and Action Names:** The names (like `rup_sc_dashextender_surecart_navigation` and `rup_sc_dashextender_dashboard_right`) must be consistent between the template and your custom functions. Any deviation will mean your custom code won’t be recognized.
    -   **Parameter Consistency:** Ensure that the number and order of parameters in your custom functions match those expected by the hooks.
2.  **Naming Conventions:**
        -   Were using a reusable framework, don't adjust filters or actions they will stop others from working so please use the `rup_sc_dashextender` naming convention and load the default template it should work for everyone!
	
    -   The keys in the navigation array (`name`, `icon_name`, `href`) are expected to be in the correct format so that the dashboard template can properly render the custom tab.

3.  **Consistency:**
When customizing your dashboard, consistency in the identifiers is key. For example, in your code the identifier is **custom_content**. Here’s what needs to stay consistent:

-   **Navigation Key:**  
    In your navigation array, you're adding a key named `'custom_content'`. This key must match the identifier used in the query parameter (set by `add_query_arg( 'sc-page', 'custom_content', get_permalink() )`).
    
-   **Action Hook Suffix:**  
    The custom action hook is named `rup_sc_dashextender_surecart_dashboard_right_custom_content`. Notice that the ending **custom_content** matches the navigation key. This pairing ensures that when the user clicks the tab (which sets `sc-page=custom_content`), the correct action hook is triggered to load the corresponding content.
    
-   **Overall Identifier:**  
    Using the same identifier **custom_content** in both the filter (navigation creation) and the action (content display) ties the two together. If you change one side (e.g., the navigation key), you must update the corresponding query parameter and action hook name so that they all refer to the same identifier.
    

This consistency is what allows the system to correctly match the navigation item with its intended content area.

	
4.  **Content Loading Options:**
    
    -   **Direct HTML:** Best when content is static and doesn’t need to be edited.
    -   **WordPress Page:** Ideal for content that you want site editors to manage.
    -   **Shortcode Rendering:** Good for dynamic or reusable content and the best option for plugin developers, you could use if statements or other options to only render the types of pages you need.