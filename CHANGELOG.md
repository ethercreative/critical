## [Unreleased] 0.0.1-alpha.3
### Improved
- Only selectors that match above fold DOM elements are included in critical if
the CSS block has multiple selectors.
- Improved `@rule` support. 

### Fixed
- Fixed element events ignoring `criticalEnabled` setting. [#5]
- Critical CSS is on longer output when critical is being generated.
- Partially fixed handling of pseudo elements (pseudo classes still an issue ☹️). [#3]

[#3]: https://github.com/ethercreative/critical/issues/3
[#5]: https://github.com/ethercreative/critical/issues/5

## 0.0.1-alpha.2 - 2018-09-19
### Changed
- Pretty much everything about when critical is generated.

### Fixed
- Fixed a bug with relative stylesheet URLs

## 0.0.1-alpha.1 - 2018-09-18
- Initial Release
