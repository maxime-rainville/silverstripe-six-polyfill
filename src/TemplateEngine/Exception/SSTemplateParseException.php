<?php

/**
 * CMS 6 Polyfill for SilverStripe\View\SSTemplateParseException
 * 
 * This class provides forward compatibility by making the CMS 6 namespace
 * available in CMS 5, allowing you to migrate your code early.
 * 
 * @package silverstripe-six-polyfill
 */

namespace SilverStripe\TemplateEngine\Exception;

use Exception;
use SilverStripe\Dev\Deprecation;
/**
 * This is the exception raised when failing to parse a template. Note that we don't currently do any static analysis,
 * so we can't know if the template will run, just if it's malformed. It also won't catch mistakes that still look
 * valid.
 *
 * @deprecated 5.4.0 Will be renamed to SilverStripe\TemplateEngine\Exception\SSTemplateParseException
 */
class SSTemplateParseException extends Exception
{
    /**
     * SSTemplateParseException constructor.
     * @param string $message
     * @param SSTemplateParser $parser
     */
    public function __construct($message, $parser)
    {
        $prior = substr($parser->string ?? '', 0, $parser->pos);
        preg_match_all('/\\r\\n|\\r|\\n/', $prior ?? '', $matches);
        $line = count($matches[0] ?? []) + 1;
        parent::__construct("Parse error in template on line {$line}. Error was: {$message}");
    }
}