# Let's Get Critical
A Critical CSS generator for Craft CMS

## Usage

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