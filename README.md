# Let's Get Critical
A Critical CSS generator for Craft CMS.

Currently supports entries only.

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
{% endblock %}
```

The CSS will be saved into a file in your templates directory `@templates/_critical/[filename].css`;

## Upcoming Features
- [ ] Support all possible templates (products, categories, static, etc.)
- [ ] Ability to ignore certain CSS files.
- [ ] Customize Critical CSS save location.
- [ ] Ability to clear all caches for a certain template
- [ ] Option to generate missing critical on page load