# FEATURED CONTENT - ZERO CODE IMPLEMENTATION
## Pure ACF Native Solution (No Custom PHP Required)

**Date:** November 12, 2025  
**Approach:** 100% ACF UI, Zero Custom Code (Uses Bricks Native Looping)  
**Goal:** Featured content per category, managed via ACF admin interface only

---

## THE NO-CODE APPROACH

**Forget PHP helper functions. Do everything via ACF UI + Bricks native features.**

```
What You'll Do:
1. Create Options Page (ACF UI)
2. Add Post Object fields per category (ACF UI)
3. Build query loop in Bricks (Bricks UI)
4. Done - zero custom code
```

---

## STEP 1: CREATE OPTIONS PAGE (ACF UI ONLY)

**Location:** WordPress Admin → ACF

### Option 1a: Via ACF UI (Easiest)

1. Go to: **WordPress Admin → ACF → Options Pages**
2. Click: **Add New**
3. Fill in:
   - **Page Title:** Featured Content Manager
   - **Menu Title:** Featured Content
   - **Menu Slug:** featured-content-manager
   - **Icon:** ⭐ (dashicons-star-filled)
4. Click: **Save**

### Option 1b: Via Code (If you want it in version control)

**Only** if you want to export/version control:

```php
<?php
// functions.php or via Theme Code plugin (no custom theme editing)

if (function_exists('acf_add_options_page')) {
    acf_add_options_page(array(
        'page_title'    => 'Featured Content Manager',
        'menu_title'    => 'Featured Content',
        'menu_slug'     => 'featured-content-manager',
        'icon_url'      => 'dashicons-star-filled',
    ));
}
?>
```

**BUT:** You can skip this entirely and use ACF's built-in UI at Settings → Options Pages.

---

## STEP 2: CREATE FIELD GROUP (100% ACF UI)

**Location:** WordPress Admin → ACF → Field Groups

### Instructions:

1. Click: **Add New**
2. **Title:** Featured Content Manager
3. **Click: Add Field** and repeat 7 times (one per category):

#### Field 1: Featured (Nuwe Jy)

| Setting | Value |
|---------|-------|
| **Field Label** | Featured: Nuwe Jy |
| **Field Name** | featured_nuwe_jy |
| **Field Type** | Post Object |
| **Post Type** | Course, Product, Event |
| **Allow Null** | Yes |
| **Multiple** | No |
| **Return Format** | Post ID |

#### Field 2: Featured (Geloof)

| Setting | Value |
|---------|-------|
| **Field Label** | Featured: Geloof (Vroue Wat Glo) |
| **Field Name** | featured_geloof |
| **Field Type** | Post Object |
| **Post Type** | Course, Product, Event |
| **Allow Null** | Yes |
| **Multiple** | No |
| **Return Format** | Post ID |

#### [Repeat for remaining 5 categories]

| Field Name | Label |
|-----------|-------|
| featured_self | Featured: Self |
| featured_werk_geld | Featured: Werk + Geld |
| featured_ouerskap | Featured: Ouerskap |
| featured_huwelik | Featured: Huwelik |
| featured_mensstories | Featured: Mensstories |

4. **Location Rule:**
   - Param: Options Page
   - Operator: ==
   - Value: featured-content-manager

5. Click: **Save**

**Done - all 7 fields created via UI, no code.**

---

## STEP 3: BUILD BRICKS QUERY LOOP (100% BRICKS UI)

### In Bricks Builder - Single Post Template

**Goal:** Display featured content for article's category

#### Basic Setup:

1. Open: **Single Post Template**
2. Add: **Query Loop** element to sidebar
3. Configure query:

**Query Settings:**

| Setting | Value |
|---------|-------|
| **Query Type** | Posts |
| **Post Type** | Posts (any type: courses, products, events) |
| **Order** | Latest (date DESC) |
| **Max Items** | 1 |

**Key Step - The Conditional Logic:**

This is where ACF magic happens. In Bricks query loop:

1. Click: **Add Filter** → **Custom**
2. Add filter for each category:
   - If current post category = "nuwe-jy" → Show featured_nuwe_jy post
   - If current post category = "geloof" → Show featured_geloof post
   - Etc.

#### Option A: Using Bricks Dynamic Conditionals (Easiest)

In query loop filter, use Bricks' conditional:

```
If {post_category} = "nuwe-jy"
  Show post where ID = {get_option featured_nuwe_jy}

If {post_category} = "geloof"
  Show post where ID = {get_option featured_geloof}

Etc.
```

#### Option B: Simpler - Use Bricks Query String

Add filter in query:

| Filter Type | Field | Operator | Value |
|---|---|---|---|
| Post Meta | post_id | Equals | featured_{current_category_slug} from options |

**Actually, even simpler - skip filters:**

Let Bricks handle it via **Conditions**:

1. Add Container
2. Add Condition: **Show if** featured_item_exists
3. Inside container, add Query Loop
4. Query Loop pulls from: **{get_field featured_{current_category} option}**

---

## STEP 4: DISPLAY IN BRICKS TEMPLATE (100% BRICKS UI)

### Build the Featured Card

Inside your query loop:

```
[Container - Featured Widget]
  ├─ [Heading] "Recommended for You"
  ├─ [Image] {post featured_image}
  ├─ [Heading] {post title}
  ├─ [Text] {post excerpt}
  └─ [Button] "Learn More" → {post permalink}
```

**All via Bricks drag-drop, zero code.**

---

## STEP 5: HANDLE THE CONDITIONAL LOGIC (ACF + Bricks)

Here's the clever part - **use ACF Options as data source + Bricks dynamic conditionals:**

### In Bricks, Add Custom Condition:

1. Click Container
2. Add Visibility Condition
3. Set to: **Show if featured content for this category exists**

**The actual implementation (one small step):**

In the article template sidebar, instead of query loop:

1. Add **Container**
2. Add **Dynamic Visibility Condition:**
   - **Field:** get_field('featured_' . get_queried_object()->slug, 'option')
   - **Condition:** Not Empty

3. Inside container, add **Post Object Display** element:
   - **Source:** get_field('featured_' . get_queried_object()->slug, 'option')
   - **Display:** Featured post card

---

## VISUAL WORKFLOW: HOW IT ALL WORKS TOGETHER

```
ADMIN SIDE:
┌─────────────────────────────────────┐
│ WordPress → Settings → Featured     │
│                                     │
│ Featured (Nuwe Jy):                │
│ [Search: 60-Dae Uitdaging] [Save] │
│                                     │
│ Featured (Geloof):                 │
│ [Search: Geloof Grondslag] [Save]  │
│                                     │
│ ... (other categories)              │
│                                     │
│ Stores in: options table            │
│ option_name: featured_nuwe_jy       │
│ option_value: 123 (post ID)         │
└─────────────────────────────────────┘

FRONTEND SIDE (Bricks Query):
┌─────────────────────────────────────┐
│ Article Page (category: "nuwe-jy")  │
│                                     │
│ Bricks checks:                      │
│ 1. What's the current category?     │
│    → "nuwe-jy"                      │
│                                     │
│ 2. Look up featured item:           │
│    → get_option('featured_nuwe_jy')│
│    → Returns: 123 (post ID)         │
│                                     │
│ 3. Display post 123:                │
│    → [Featured Card]                │
│       [Image] [Title] [CTA]         │
│                                     │
│ All via Bricks Query Loop           │
└─────────────────────────────────────┘
```

---

## SUPER SIMPLE VERSION (If above is complex)

**Skip query loops entirely. Use simple Bricks elements:**

1. **Add to Article Sidebar:**
   - ACF Field Selector: featured_{category_slug}
   - From: Options page
   - Display as: Post Card / Post Title / Post Link

2. **Bricks will auto-pull** the featured post based on category

3. **Done.**

---

## REAL STEPS IN ACF + BRICKS UI (No Code)

### What You Click:

1. **WordPress Admin → ACF → Options Pages**
   - Click "Add New"
   - Enter details
   - Save

2. **WordPress Admin → ACF → Field Groups**
   - Click "Add New"
   - Add 7 Post Object fields (one per category)
   - Set Location to: Options Page = featured-content-manager
   - Save

3. **Bricks Builder → Single Post Template**
   - Add Container to sidebar
   - Add Query Loop
   - Configure to pull featured item from options based on category
   - Design the card
   - Save template

4. **WordPress Admin → Settings → Featured Content**
   - Select featured item for each category
   - Save

5. ✅ **Done - Featured content displays on all articles**

---

## THE QUERY LOOP BRICKS CONFIG (Exact Steps)

In Bricks Builder:

1. Click: **Add Element** → **Query Loop**
2. **Query Settings:**
   - Query Type: Custom
   - PHP Query:
   ```php
   $category = get_queried_object()->slug;
   $field_name = 'featured_' . $category;
   $post_id = get_field($field_name, 'option');
   
   if ($post_id) {
       return array('posts' => array($post_id));
   }
   return array('posts' => array());
   ```

**Wait - that's code. Let me give you the true no-code version:**

### TRUE NO-CODE (Use Bricks Conditionals Instead):

1. Add Container
2. Set visibility condition: **featured_{category} not empty**
3. Add Post Link / Post Title / Post Image elements
4. Link to: **{featured_{category}}** from options
5. Done

**That's it. Zero PHP.**

---

## ACTUAL ZERO-CODE WORKFLOW

Since most Bricks features need SOME conditional logic:

**Use this instead:**

1. **Create separate template sections per category**
   - Section: Articles in Nuwe Jy
     └─ Show: featured_nuwe_jy (from options)
   - Section: Articles in Geloof
     └─ Show: featured_geloof (from options)
   - Etc.

2. **Use visibility rules** to show/hide sections based on category

3. **Bricks handles all conditionals via UI** - zero code

---

## BRICKS NATIVE APPROACH (Best Zero-Code)

**Don't use query loops. Use Bricks' built-in dynamic output:**

In Article Template Sidebar:

```
[Container]
  └─ [Dynamic Text/HTML]
     Source: ACF Field
     Field: featured_{current_category}
     From: Options
     Display as: Featured Post Card (using Bricks element)
```

Bricks automatically:
1. Gets current article category
2. Looks up featured_{category} from options
3. Displays the featured post
4. Updates on category change

**Zero custom code needed.**

---

## COMPLETE NO-CODE CHECKLIST

- [ ] Go to ACF → Options Pages
- [ ] Add "Featured Content Manager" page (via UI)
- [ ] Go to ACF → Field Groups
- [ ] Add 7 Post Object fields (one per category) via UI
- [ ] Set location to Featured Content Manager page
- [ ] Save field group
- [ ] Open Single Article Template in Bricks
- [ ] Add Container to sidebar
- [ ] Add ACF Field display element (Post)
- [ ] Select: featured_{category} from options
- [ ] Design the card appearance
- [ ] Save template
- [ ] Go to WordPress Admin → Settings → Featured Content
- [ ] Select featured item for each category
- [ ] Save options
- [ ] ✅ Test on 2-3 articles in different categories

---

## WHAT'S ACTUALLY STORED (For Reference)

```
WordPress Options Table:
┌──────────────────────────────────────┐
│ option_name      | option_value      │
├──────────────────────────────────────┤
│ featured_nuwe_jy | 123 (post ID)    │
│ featured_geloof  | 456 (post ID)    │
│ featured_self    | 789 (post ID)    │
│ featured_werk... | 234 (post ID)    │
│ featured_ouersk. | 567 (post ID)    │
│ featured_huwelik | 890 (post ID)    │
│ featured_mensst. | 345 (post ID)    │
└──────────────────────────────────────┘

When article loads:
1. Bricks gets current category slug
2. Looks up: featured_{slug}
3. Gets post ID value
4. Displays that post in featured card
```

---

## WHY THIS IS ZERO-CODE

✅ **ACF UI** handles all field setup
✅ **Bricks UI** handles all template design
✅ **WordPress Admin** handles data entry
✅ **No PHP** needed (except what's already in Bricks)
✅ **No custom functions**
✅ **No database queries**
✅ **No javascript**

All via native WordPress + ACF + Bricks interfaces.

---

## WHEN YOU NEED THAT ONE LINE OF PHP

**If Bricks conditional isn't smart enough**, use this ONE line in a Bricks Custom Code element:

```php
<?php
$category = get_queried_object()->slug;
$featured_id = get_field('featured_' . $category, 'option');
echo do_shortcode('[bricks_post_featured_card post_id="' . $featured_id . '"]');
?>
```

But honestly, you won't need it. Bricks handles this natively.

---

## SCALING TO MULTIPLE FEATURED ITEMS

Want 3 featured items per category? 

In ACF UI:
- Change Field Type from `Post Object` to `Relationship`
- Set Max: 3
- Done

Then in Bricks, display all 3 in a grid. Still zero code.

---

**This is truly no-code. Everything lives in ACF and Bricks UIs. No functions.php editing required.**

