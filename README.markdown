## PLEASE NOTE: This is early code, and bugs are still present.

PHPDocxToEPub allows for a very basic conversion of a docx document to an ePub file.

The goal is to make a CLEAN xhtml output.

## License
LGPL 2.1

## Requirements
* ePub (git: grandt/PHPePub) must be installed in the ePub sub directory.
* (optional) Html purifier will be used if it is in the htmlpurifier directory, (path : htmlpurifier/library/HTMLPurifier.auto.php )

### Completed:
* It'll use TOC bookmarks to split the docx file into chapters.
* Page breaks will be obeyed, splitting the chapter into multiple files.
* Most basic Word formatting (italic, bold, underline, strikethrough, etc.) are implemented, but I need help there.
* Basic tables are implemented, including merged column and rows

### Todo
* Bullet lists
* More formatting styles (some are not yet working right)
* Maybe reading the actual documents styling of headers and body text.
* TOC Links are not rewritten to reference the actual file generated. 
* Major clean up.

### Formatting styles
I'm usingth Resources/WordStyles.css to format these. Apart from Italic and bold all the otrher formats are implemented in a span, with multiple references in the class attribute. (underline superscript is [span class="underline superscript"]...[/span]

I am stuck with Docx' rather odd markup, so if you have offset styling, so you'll see something like for instance [em]something [strong]bold[/strong][/em][strong] goes here[/strong]

## IMPORTANT
The code is provided AS IS, and it is still VERY early in development. 
The target for the script is only basic formatting support, I have no intention of expanding much beyond what is in the H1.docx example.
The code is a mess, I know. Trial and error is maybe not the best approach after all :P

## Acknowledgements
The code is based on a php script by Jack Reichert, his code can be found here: http://www.jackreichert.com/2012/11/09/how-to-convert-docx-to-html/