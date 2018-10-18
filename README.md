# Let's Get Critical
A Critical CSS generator for Craft CMS.

## Usage

Add the `critical-css` hook to your head tag:
```twig
<head>
    {# ... #}
    
    {% hook 'critical-css' %}
    
    {# ... #}
</head>
```

Wrap anything you consider to be above the fold with fold tags:

```twig
{% fold %}

...

{% endfold %}
```

The fold tag supports an If statement, useful when using the tag in a loop:

```twig
{% for block in entry.pageContent %}
    {% fold if loop.index < 2 %}
        ...
    {% endfold %}
{% endfor %}
```

## Upcoming Features
- [ ] Ability to ignore certain CSS files.
- [x] Support all possible templates (products, categories, static, etc.)
- [ ] Ability to clear all caches for a certain template
- [ ] Option to generate missing critical on page load
- [x] Customize Critical CSS save location.
