src/Model/ArrayData.php:86
`instanceof ViewableData` got switch to `instanceof ModelData`
This kind of thing is likely to bread this file and other file.


similar thing on `src/Model/List/ArrayList.php:534`

Yeah, this is not going to work. Because some method will explicitely expect a type and if you give them to new type they will fall on their face.

Best we can do, is an alias list.

Here's an idea:
- Rather than just copy a few existing class, fork the entire repo
- Rename the class to their new one name
- Set up a PHP Alias for the old one.
- Still remove the deprecation warning, but trigger a special PHPStan rule that will warn when the Alias is used instead of final name.
- People would have to install the fork of framework, which is a lot less clean.
