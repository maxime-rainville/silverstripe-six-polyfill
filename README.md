# Silverstripe CMS 6 polyfill for CMS 5

Silverstripe CMS 6 moved a bunch of classes around. e.g.: `SilverStripe\ORM\ArrayList` got moved to `SilverStripe\Model\List\ArrayList`.

So in CMS 5, many classes will now throw deprecation warning about the class being moved to a different namespace. However, since these new classes don't exist yet, you can't fix the deprecation warning without upgrading to CMS 6.

```php
public function __construct(array $items = [])
{
    Deprecation::withSuppressedNotice(function () {
        Deprecation::notice('5.4.0', 'Will be renamed to SilverStripe\Model\List\ArrayList', Deprecation::SCOPE_CLASS);
    });

    $this->items = array_values($items ?? []);
    parent::__construct();
}
```

This packages ships copies of the current CMS 5 classes but moved to their CMS 6 namespace. It removes the deprecation warning from the constructor, that way you can adopt the new namespace in CMS 5.


## Using this module

1. Install the module to your project.
```bash
composer run archiprocode/silverstripe-six-poylfill
```
2. Update references to deprecated classes to their CMS 6 equivalent to suppress the "Will be renamed" deprecation warning.

Note that this only addressed the "Will be renamed" deprecation. Other APIs on these classes may be derpcetade for other reasons.

## How this module is generated

This modules aims to be maintanable with the latest silverstripe CMS 5 release. Sa a process has been put in place so it can easily refresh itself as needed.

- The file cms6-equivalence.yml contain a list of CMS5 class name and what their equivalence in CMS 6 is.
- the refresh.php script
  - checkouts the latest stable tags of `silverstripe/framework` 5.
  - copy each file reference in cms6-equivalence.yml to its new spot within this module.
  - use phpstan to remove the deprecation warning from the class contsructor while preserving other deprocation warnings within the class.