<?php

#
#
# Parsedown
# http://parsedown.org
#
# (c) Emanuil Rusev
# http://erusev.com
#
# For the full license information, view the LICENSE file that was distributed
# with this source code.
#
#

class Parsedown
{
    # ~

    const version = '1.8.0-beta-7';

    # ~

    /**
     * Processes a given text string, converting it into markup and trimming extra line breaks.
     * @example
     * const markup = text("Hello, **World**!");
     * console.log(markup); // Outputs: "<p>Hello, <strong>World</strong>!</p>"
     * @param {string} text - The input text to be processed and converted to markup.
     * @returns {string} The processed markup string with extraneous line breaks removed.
     * @description
     *   - Utilizes two internal methods: `textElements` for parsing and `elements` for markup conversion.
     *   - Trims any leading or trailing newline characters from the resulting markup.
     */
    function text($text)
    {
        $Elements = $this->textElements($text);

        # convert to markup
        $markup = $this->elements($Elements);

        # trim line breaks
        $markup = trim($markup, "\n");

        return $markup;
    }

    /**
     * Processes a given text to handle and standardize line breaks, split it into lines, 
     * and then identify text blocks within it.
     * @example
     * const result = textElements("This is a sample text\nwith multiple lines.\r\nAnother line.");
     * console.log(result); // Renders the parsed line elements, e.g., [{type: 'line', content: 'This is a sample text'}, ...]
     * @param {string} text - The text input that needs to be processed.
     * @returns {Array} An array of parsed line elements from the input text.
     * @description
     *   - Converts all types of line breaks in the input text to a standard line break (\n).
     *   - Trims any leading or trailing line breaks from the entire text string.
     *   - Splits the standardized text into individual lines for further processing.
     */
    protected function textElements($text)
    {
        # make sure no definitions are set
        $this->DefinitionData = array();

        # standardize line breaks
        $text = str_replace(array("\r\n", "\r"), "\n", $text);

        # remove surrounding line breaks
        $text = trim($text, "\n");

        # split text into lines
        $lines = explode("\n", $text);

        # iterate through lines to identify blocks
        return $this->linesElements($lines);
    }

    #
    # Setters
    #

    function setBreaksEnabled($breaksEnabled)
    {
        $this->breaksEnabled = $breaksEnabled;

        return $this;
    }

    protected $breaksEnabled;

    function setMarkupEscaped($markupEscaped)
    {
        $this->markupEscaped = $markupEscaped;

        return $this;
    }

    protected $markupEscaped;

    function setUrlsLinked($urlsLinked)
    {
        $this->urlsLinked = $urlsLinked;

        return $this;
    }

    protected $urlsLinked = true;

    function setSafeMode($safeMode)
    {
        $this->safeMode = (bool) $safeMode;

        return $this;
    }

    protected $safeMode;

    function setStrictMode($strictMode)
    {
        $this->strictMode = (bool) $strictMode;

        return $this;
    }

    protected $strictMode;

    protected $safeLinksWhitelist = array(
        'http://',
        'https://',
        'ftp://',
        'ftps://',
        'mailto:',
        'tel:',
        'data:image/png;base64,',
        'data:image/gif;base64,',
        'data:image/jpeg;base64,',
        'irc:',
        'ircs:',
        'git:',
        'ssh:',
        'news:',
        'steam:',
    );

    #
    # Lines
    #

    protected $BlockTypes = array(
        '#' => array('Header'),
        '*' => array('Rule', 'List'),
        '+' => array('List'),
        '-' => array('SetextHeader', 'Table', 'Rule', 'List'),
        '0' => array('List'),
        '1' => array('List'),
        '2' => array('List'),
        '3' => array('List'),
        '4' => array('List'),
        '5' => array('List'),
        '6' => array('List'),
        '7' => array('List'),
        '8' => array('List'),
        '9' => array('List'),
        ':' => array('Table'),
        '<' => array('Comment', 'Markup'),
        '=' => array('SetextHeader'),
        '>' => array('Quote'),
        '[' => array('Reference'),
        '_' => array('Rule'),
        '`' => array('FencedCode'),
        '|' => array('Table'),
        '~' => array('FencedCode'),
    );

    # ~

    protected $unmarkedBlockTypes = array(
        'Code',
    );

    #
    # Blocks
    #

    protected function lines(array $lines)
    {
        return $this->elements($this->linesElements($lines));
    }

    /**
     * Processes an array of strings and returns an array of elements.
     * @example
     * const linesArray = ["First line", "  Second line with indent", ""];
     * const result = linesElements(linesArray);
     * console.log(result); // Outputs parsed elements with their structure.
     * @param {Array<string>} lines - An array of strings representing lines of text.
     * @returns {Array<Object>} An array of elements that represent parsed block structures.
     * @description
     *   - This function transforms lines of text into structured elements by identifying block types.
     *   - Handles indentation and tab characters to align text properly within elements.
     *   - Manages continuability and completion of different block types during parsing.
     *   - Utilizes helper functions to process each specific block type.
     */
    protected function linesElements(array $lines)
    {
        $Elements = array();
        $CurrentBlock = null;

        foreach ($lines as $line)
        {
            if (chop($line) === '')
            {
                if (isset($CurrentBlock))
                {
                    $CurrentBlock['interrupted'] = (isset($CurrentBlock['interrupted'])
                        ? $CurrentBlock['interrupted'] + 1 : 1
                    );
                }

                continue;
            }

            while (($beforeTab = strstr($line, "\t", true)) !== false)
            {
                $shortage = 4 - mb_strlen($beforeTab, 'utf-8') % 4;

                $line = $beforeTab
                    . str_repeat(' ', $shortage)
                    . substr($line, strlen($beforeTab) + 1)
                ;
            }

            $indent = strspn($line, ' ');

            $text = $indent > 0 ? substr($line, $indent) : $line;

            # ~

            $Line = array('body' => $line, 'indent' => $indent, 'text' => $text);

            # ~

            if (isset($CurrentBlock['continuable']))
            {
                $methodName = 'block' . $CurrentBlock['type'] . 'Continue';
                $Block = $this->$methodName($Line, $CurrentBlock);

                if (isset($Block))
                {
                    $CurrentBlock = $Block;

                    continue;
                }
                else
                {
                    if ($this->isBlockCompletable($CurrentBlock['type']))
                    {
                        $methodName = 'block' . $CurrentBlock['type'] . 'Complete';
                        $CurrentBlock = $this->$methodName($CurrentBlock);
                    }
                }
            }

            # ~

            $marker = $text[0];

            # ~

            $blockTypes = $this->unmarkedBlockTypes;

            if (isset($this->BlockTypes[$marker]))
            {
                foreach ($this->BlockTypes[$marker] as $blockType)
                {
                    $blockTypes []= $blockType;
                }
            }

            #
            # ~

            foreach ($blockTypes as $blockType)
            {
                $Block = $this->{"block$blockType"}($Line, $CurrentBlock);

                if (isset($Block))
                {
                    $Block['type'] = $blockType;

                    if ( ! isset($Block['identified']))
                    {
                        if (isset($CurrentBlock))
                        {
                            $Elements[] = $this->extractElement($CurrentBlock);
                        }

                        $Block['identified'] = true;
                    }

                    if ($this->isBlockContinuable($blockType))
                    {
                        $Block['continuable'] = true;
                    }

                    $CurrentBlock = $Block;

                    continue 2;
                }
            }

            # ~

            if (isset($CurrentBlock) and $CurrentBlock['type'] === 'Paragraph')
            {
                $Block = $this->paragraphContinue($Line, $CurrentBlock);
            }

            if (isset($Block))
            {
                $CurrentBlock = $Block;
            }
            else
            {
                if (isset($CurrentBlock))
                {
                    $Elements[] = $this->extractElement($CurrentBlock);
                }

                $CurrentBlock = $this->paragraph($Line);

                $CurrentBlock['identified'] = true;
            }
        }

        # ~

        if (isset($CurrentBlock['continuable']) and $this->isBlockCompletable($CurrentBlock['type']))
        {
            $methodName = 'block' . $CurrentBlock['type'] . 'Complete';
            $CurrentBlock = $this->$methodName($CurrentBlock);
        }

        # ~

        if (isset($CurrentBlock))
        {
            $Elements[] = $this->extractElement($CurrentBlock);
        }

        # ~

        return $Elements;
    }

    /**
     * Extracts the 'element' from a given component array, or creates it based on other keys.
     * @example
     * let component = { markup: '<p>Hello World</p>' };
     * let element = extractElement(component);
     * console.log(element); // { rawHtml: '<p>Hello World</p>' }
     * @param {Object} Component - The component object that may contain 'element', 'markup', or 'hidden' properties.
     * @returns {Object} Returns the 'element' if it exists; otherwise, constructs and returns it based on the presence of 'markup' or 'hidden' properties.
     * @description
     *   - If 'element' is not set, tries to create it using 'markup' or sets it as an empty object if 'hidden' is present.
     *   - This function assumes that 'component' is a defined and valid object.
     */
    protected function extractElement(array $Component)
    {
        if ( ! isset($Component['element']))
        {
            if (isset($Component['markup']))
            {
                $Component['element'] = array('rawHtml' => $Component['markup']);
            }
            elseif (isset($Component['hidden']))
            {
                $Component['element'] = array();
            }
        }

        return $Component['element'];
    }

    protected function isBlockContinuable($Type)
    {
        return method_exists($this, 'block' . $Type . 'Continue');
    }

    protected function isBlockCompletable($Type)
    {
        return method_exists($this, 'block' . $Type . 'Complete');
    }

    #
    # Code

    /**
     * Processes a line of code and determines its block structure for rendering.
     * @example
     * const line = { indent: 4, body: '    console.log("Hello, World!");' };
     * const block = blockCode(line);
     * console.log(block); // { element: { name: 'pre', element: { name: 'code', text: 'console.log("Hello, World!");' } } }
     * @param {Object} Line - An object representing a line of text, containing properties such as indent and body.
     * @param {Object|null} [Block=null] - An optional object representing the current block state, containing information about type and interruption status.
     * @returns {Object|undefined} Returns a block object formatted for rendering, or undefined if conditions are not met.
     * @description
     *   - Converts lines with an indent of 4 or more spaces into a code block.
     *   - If Block is of type 'Paragraph' and not interrupted, function returns undefined.
     *   - Use this function to transform lines of code into preformatted blocks for display.
     */
    protected function blockCode($Line, $Block = null)
    {
        if (isset($Block) and $Block['type'] === 'Paragraph' and ! isset($Block['interrupted']))
        {
            return;
        }

        if ($Line['indent'] >= 4)
        {
            $text = substr($Line['body'], 4);

            $Block = array(
                'element' => array(
                    'name' => 'pre',
                    'element' => array(
                        'name' => 'code',
                        'text' => $text,
                    ),
                ),
            );

            return $Block;
        }
    }

    /**
     * Continues a code block if the line has the required indentation level.
     * @example
     * const line = { indent: 4, body: '    console.log("Hello, World!");' };
     * const block = { element: { element: { text: 'code' } } };
     * const result = blockCodeContinue(line, block);
     * // result is the block with the continued code text appended.
     * @param {Object} Line - An object representing a line of code with properties for indentation level and body text.
     * @param {Object} Block - An object representing the current state of the code block being constructed.
     * @returns {Object} The updated Block object with continued code.
     * @description
     *   - Appends interrupted lines with new lines proportional to the number of interruptions before adding the code.
     *   - Strips the first four characters (indentation) from the line body before appending.
     */
    protected function blockCodeContinue($Line, $Block)
    {
        if ($Line['indent'] >= 4)
        {
            if (isset($Block['interrupted']))
            {
                $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

                unset($Block['interrupted']);
            }

            $Block['element']['element']['text'] .= "\n";

            $text = substr($Line['body'], 4);

            $Block['element']['element']['text'] .= $text;

            return $Block;
        }
    }

    protected function blockCodeComplete($Block)
    {
        return $Block;
    }

    #
    # Comment

    /**
     * Processes a block comment line to determine if it starts with '<!--' and appends it to an HTML block if appropriate.
     * @example
     * $line = ['text' => '<!-- This is a comment -->', 'body' => '<!-- This is a comment -->'];
     * $block = blockComment($line);
     * print_r($block); // Outputs: Array ( [element] => Array ( [rawHtml] => <!-- This is a comment --> [autobreak] => 1 ) [closed] => 1 )
     * @param {array} $Line - An array representing a line of text, with keys 'text' and 'body'.
     * @returns {array|null} Returns an associative array representing an HTML block if the line starts with '<!--', null otherwise.
     * @description
     *   - Handles comment blocks embedded within a text line starting with '<!--' and optionally ending with '-->'.
     *   - When $markupEscaped or $safeMode is enabled, the function immediately returns.
     *   - The 'autobreak' property in the HTML block is set to true to ensure appropriate rendering breaks in output.
     *   - If the closing '-->' is present within the text, the block is marked as 'closed'.
     */
    protected function blockComment($Line)
    {
        if ($this->markupEscaped or $this->safeMode)
        {
            return;
        }

        if (strpos($Line['text'], '<!--') === 0)
        {
            $Block = array(
                'element' => array(
                    'rawHtml' => $Line['body'],
                    'autobreak' => true,
                ),
            );

            if (strpos($Line['text'], '-->') !== false)
            {
                $Block['closed'] = true;
            }

            return $Block;
        }
    }

    /**
     * Continues a block comment with a given line and updates the block status.
     * @example
     * let line = { body: 'extra line', text: 'extra line text' };
     * let block = { element: { rawHtml: '<!-- comment' } };
     * let result = blockCommentContinue(line, block);
     * console.log(result); // { element: { rawHtml: '<!-- comment\nextra line' }, closed: undefined }
     * @param {Object} Line - The line object containing 'body' and 'text' properties.
     * @param {Array} Block - The block array which holds current comment block details.
     * @returns {Array|undefined} Updated block array with new line appended, or undefined if already closed.
     * @description
     *   - Appends the line body to the rawHtml of the block element.
     *   - Marks the block as closed if the end of comment marker '-->' is found in the line text.
     */
    protected function blockCommentContinue($Line, array $Block)
    {
        if (isset($Block['closed']))
        {
            return;
        }

        $Block['element']['rawHtml'] .= "\n" . $Line['body'];

        if (strpos($Line['text'], '-->') !== false)
        {
            $Block['closed'] = true;
        }

        return $Block;
    }

    #
    # Fenced Code

    /**
     * Processes fenced code blocks and returns a structured representation for rendering.
     * @example
     * $line = array('text' => '```php');
     * $result = $this->blockFencedCode($line);
     * // Result: array with 'char', 'openerLength', and 'element' detailing the code block.
     * @param array $Line - An associative array representing the line of text, with 'text' being the code block.
     * @returns array|null A structured array with information about the fenced code block, or null if invalid.
     * @description
     *   - Recognizes and processes fence markers for code blocks.
     *   - Applies language class if infostring is present and valid.
     *   - Returns null if the opener length is less than three or infostring contains backticks.
     */
    protected function blockFencedCode($Line)
    {
        $marker = $Line['text'][0];

        $openerLength = strspn($Line['text'], $marker);

        if ($openerLength < 3)
        {
            return;
        }

        $infostring = trim(substr($Line['text'], $openerLength), "\t ");

        if (strpos($infostring, '`') !== false)
        {
            return;
        }

        $Element = array(
            'name' => 'code',
            'text' => '',
        );

        if ($infostring !== '')
        {
            /**
             * https://www.w3.org/TR/2011/WD-html5-20110525/elements.html#classes
             * Every HTML element may have a class attribute specified.
             * The attribute, if specified, must have a value that is a set
             * of space-separated tokens representing the various classes
             * that the element belongs to.
             * [...]
             * The space characters, for the purposes of this specification,
             * are U+0020 SPACE, U+0009 CHARACTER TABULATION (tab),
             * U+000A LINE FEED (LF), U+000C FORM FEED (FF), and
             * U+000D CARRIAGE RETURN (CR).
             */
            $language = substr($infostring, 0, strcspn($infostring, " \t\n\f\r"));

            $Element['attributes'] = array('class' => "language-$language");
        }

        $Block = array(
            'char' => $marker,
            'openerLength' => $openerLength,
            'element' => array(
                'name' => 'pre',
                'element' => $Element,
            ),
        );

        return $Block;
    }

    /**
     * Continues the processing of a fenced code block.
     * @example
     * let line = { text: "```", body: "code here" };
     * let block = { char: "`", openerLength: 3, element: { element: { text: "\n" } } };
     * let result = blockFencedCodeContinue(line, block);
     * console.log(result); // Example output reflecting updated block structure.
     * @param {Object} Line - The current line being processed. Should have properties `text` and `body`.
     * @param {Object} Block - The current state of the block being constructed. Should include `element`, `char`, and `openerLength`.
     * @returns {Object|undefined} Updated block object if completed, otherwise returns undefined.
     * @description
     *   - This function checks if the current line signals the end of the fenced code block.
     *   - If the block is interrupted, it appends the appropriate number of newline characters.
     *   - It ensures the first character of the block text is omitted if the block completes.
     *   - The function modifies the block in place to include the current line's body text.
     */
    protected function blockFencedCodeContinue($Line, $Block)
    {
        if (isset($Block['complete']))
        {
            return;
        }

        if (isset($Block['interrupted']))
        {
            $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

            unset($Block['interrupted']);
        }

        if (($len = strspn($Line['text'], $Block['char'])) >= $Block['openerLength']
            and chop(substr($Line['text'], $len), ' ') === ''
        ) {
            $Block['element']['element']['text'] = substr($Block['element']['element']['text'], 1);

            $Block['complete'] = true;

            return $Block;
        }

        $Block['element']['element']['text'] .= "\n" . $Line['body'];

        return $Block;
    }

    protected function blockFencedCodeComplete($Block)
    {
        return $Block;
    }

    #
    # Header

    /**
     * Parses a markdown header line and returns an element block.
     *
     * @param {Object} Line - The line object containing text to be parsed.
     * @param {string} Line.text - The text of the line, typically starting with '#' characters for header level.
     * @returns {Object|undefined} Returns an element block containing header information or undefined if header level exceeds 6 or in strict mode.
     * @example
     * const markdownLine = { text: '## Header Text' };
     * const block = blockHeader(markdownLine);
     * console.log(block);
     * // Output: { element: { name: 'h2', handler: { function: 'lineElements', argument: 'Header Text', destination: 'elements' } } }
     * @description
     * - Header levels are determined by the number of '#' characters at the beginning of the line.
     * - Strict mode requires a space after the '#' characters for valid headers.
     */
    protected function blockHeader($Line)
    {
        $level = strspn($Line['text'], '#');

        if ($level > 6)
        {
            return;
        }

        $text = trim($Line['text'], '#');

        if ($this->strictMode and isset($text[0]) and $text[0] !== ' ')
        {
            return;
        }

        $text = trim($text, ' ');

        $Block = array(
            'element' => array(
                'name' => 'h' . $level,
                'handler' => array(
                    'function' => 'lineElements',
                    'argument' => $text,
                    'destination' => 'elements',
                )
            ),
        );

        return $Block;
    }

    #
    # List

    /**
     * Parse a line and create a block for ordered or unordered list.
     * @example
     * const line = {text: "- Item 1"};
     * const currentBlock = null;
     * const result = blockList(line, currentBlock);
     * console.log(result);
     * // Output: { indent: undefined, pattern: '[*+-]', data: { type: 'ul', marker: '- ', markerType: '-' }, ... }
     * @param {Object} Line - An object containing the text of a line. Example: {text: "- Item 1"}
     * @param {Array} [CurrentBlock=null] - Optional. Current block state to modify or check. Example: null
     * @returns {Object} Returns a block object for the list element. Example returns: { indent: 0, pattern: '[*+-]', data: { type: 'ul', marker: '-', markerType: '-' }, ... }
     * @description
     *   - Handles both ordered ('ol') and unordered ('ul') lists based on the line starting character.
     *   - Adjusts the content indentation within the list items.
     *   - Determines list type and marker type to construct the block data.
     *   - In the case of ordered lists, computes the starting number, adjusting it when necessary.
     */
    protected function blockList($Line, array $CurrentBlock = null)
    {
        list($name, $pattern) = $Line['text'][0] <= '-' ? array('ul', '[*+-]') : array('ol', '[0-9]{1,9}+[.\)]');

        if (preg_match('/^('.$pattern.'([ ]++|$))(.*+)/', $Line['text'], $matches))
        {
            $contentIndent = strlen($matches[2]);

            if ($contentIndent >= 5)
            {
                $contentIndent -= 1;
                $matches[1] = substr($matches[1], 0, -$contentIndent);
                $matches[3] = str_repeat(' ', $contentIndent) . $matches[3];
            }
            elseif ($contentIndent === 0)
            {
                $matches[1] .= ' ';
            }

            $markerWithoutWhitespace = strstr($matches[1], ' ', true);

            $Block = array(
                'indent' => $Line['indent'],
                'pattern' => $pattern,
                'data' => array(
                    'type' => $name,
                    'marker' => $matches[1],
                    'markerType' => ($name === 'ul' ? $markerWithoutWhitespace : substr($markerWithoutWhitespace, -1)),
                ),
                'element' => array(
                    'name' => $name,
                    'elements' => array(),
                ),
            );
            $Block['data']['markerTypeRegex'] = preg_quote($Block['data']['markerType'], '/');

            if ($name === 'ol')
            {
                $listStart = ltrim(strstr($matches[1], $Block['data']['markerType'], true), '0') ?: '0';

                if ($listStart !== '1')
                {
                    if (
                        isset($CurrentBlock)
                        and $CurrentBlock['type'] === 'Paragraph'
                        and ! isset($CurrentBlock['interrupted'])
                    ) {
                        return;
                    }

                    $Block['element']['attributes'] = array('start' => $listStart);
                }
            }

            $Block['li'] = array(
                'name' => 'li',
                'handler' => array(
                    'function' => 'li',
                    'argument' => !empty($matches[3]) ? array($matches[3]) : array(),
                    'destination' => 'elements'
                )
            );

            $Block['element']['elements'] []= & $Block['li'];

            return $Block;
        }
    }

    /**
     * Continue processing a block list element in markdown parsing.
     * @example
     * const newBlock = blockListContinue(lineData, blockData);
     * if (newBlock) {
     *   console.log('Updated block list:', newBlock);
     * }
     * @param {Object} Line - An object representing the current line being parsed, containing properties such as 'indent' and 'text'.
     * @param {Object[]} Block - An array of block objects representing the current state of block parsing, including their properties like 'indent' and 'li'.
     * @returns {Object|null} Returns the updated block object if conditions are met; otherwise, returns null.
     * @description
     *   - Handles the continuation of ordered ('ol') and unordered ('ul') list blocks by inspecting indentation and marker types.
     *   - Adjusts the block if it is interrupted, allowing list items to be marked as 'loose' if necessary.
     *   - Ensures that the correct level of indentation is maintained for continued list processing.
     */
    protected function blockListContinue($Line, array $Block)
    {
        if (isset($Block['interrupted']) and empty($Block['li']['handler']['argument']))
        {
            return null;
        }

        $requiredIndent = ($Block['indent'] + strlen($Block['data']['marker']));

        if ($Line['indent'] < $requiredIndent
            and (
                (
                    $Block['data']['type'] === 'ol'
                    and preg_match('/^[0-9]++'.$Block['data']['markerTypeRegex'].'(?:[ ]++(.*)|$)/', $Line['text'], $matches)
                ) or (
                    $Block['data']['type'] === 'ul'
                    and preg_match('/^'.$Block['data']['markerTypeRegex'].'(?:[ ]++(.*)|$)/', $Line['text'], $matches)
                )
            )
        ) {
            if (isset($Block['interrupted']))
            {
                $Block['li']['handler']['argument'] []= '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            unset($Block['li']);

            $text = isset($matches[1]) ? $matches[1] : '';

            $Block['indent'] = $Line['indent'];

            $Block['li'] = array(
                'name' => 'li',
                'handler' => array(
                    'function' => 'li',
                    'argument' => array($text),
                    'destination' => 'elements'
                )
            );

            $Block['element']['elements'] []= & $Block['li'];

            return $Block;
        }
        elseif ($Line['indent'] < $requiredIndent and $this->blockList($Line))
        {
            return null;
        }

        if ($Line['text'][0] === '[' and $this->blockReference($Line))
        {
            return $Block;
        }

        if ($Line['indent'] >= $requiredIndent)
        {
            if (isset($Block['interrupted']))
            {
                $Block['li']['handler']['argument'] []= '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            $text = substr($Line['body'], $requiredIndent);

            $Block['li']['handler']['argument'] []= $text;

            return $Block;
        }

        if ( ! isset($Block['interrupted']))
        {
            $text = preg_replace('/^[ ]{0,'.$requiredIndent.'}+/', '', $Line['body']);

            $Block['li']['handler']['argument'] []= $text;

            return $Block;
        }
    }

    /**
     * Completes processing of a block list if the 'loose' attribute is set.
     * @example
     * const block = {
     *   loose: true,
     *   element: {
     *     elements: [
     *       { handler: { argument: ['item1'] } },
     *       { handler: { argument: ['item2'] } }
     *     ]
     *   }
     * };
     * const result = blockListComplete(block);
     * // result: {
     * //   loose: true,
     * //   element: {
     * //     elements: [
     * //       { handler: { argument: ['item1', ''] } },
     * //       { handler: { argument: ['item2', ''] } }
     * //     ]
     * //   }
     * // }
     * @param {Object} Block - The block structure containing elements to process.
     * @returns {Object} The updated block structure after processing.
     * @description
     *   - Adds an empty string to the 'argument' array of each list item if the block is 'loose' and
     *     the last element of the 'argument' array is not an empty string.
     *   - Intended for adjusting the content structure for rendering or further processing.
     */
    protected function blockListComplete(array $Block)
    {
        if (isset($Block['loose']))
        {
            foreach ($Block['element']['elements'] as &$li)
            {
                if (end($li['handler']['argument']) !== '')
                {
                    $li['handler']['argument'] []= '';
                }
            }
        }

        return $Block;
    }

    #
    # Quote

    /**
     * Parses a Markdown blockquote from a given line of text.
     * @example
     * const line = { text: '> This is a blockquote' };
     * const result = blockQuote(line);
     * console.log(result);
     * // Output:
     * // {
     * //   element: {
     * //     name: 'blockquote',
     * //     handler: {
     * //       function: 'linesElements',
     * //       argument: ['This is a blockquote'],
     * //       destination: 'elements'
     * //     }
     * //   }
     * // }
     * @param {Object} Line - Object containing the text to be parsed, with `text` as the key.
     * @returns {Object|undefined} An object representing the blockquote as an HTML element, or undefined if the line doesn't start with '>'.
     * @description
     *   - Utilizes a regular expression to determine if a line starts with '>'.
     *   - Converts the matched text following '>' into a blockquote HTML element.
     */
    protected function blockQuote($Line)
    {
        if (preg_match('/^>[ ]?+(.*+)/', $Line['text'], $matches))
        {
            $Block = array(
                'element' => array(
                    'name' => 'blockquote',
                    'handler' => array(
                        'function' => 'linesElements',
                        'argument' => (array) $matches[1],
                        'destination' => 'elements',
                    )
                ),
            );

            return $Block;
        }
    }

    /**
     * Continue a blockquote with provided line and block details.
     * @example
     * const line = { text: '> This is a blockquote' };
     * const block = { element: { handler: { argument: [] } } };
     * const result = blockQuoteContinue(line, block);
     * console.log(result); // { element: { handler: { argument: ['This is a blockquote'] } } }
     * @param {Object} Line - An object representing the line, containing a 'text' property.
     * @param {Object} Block - An object representing the current block structure being parsed.
     * @returns {Object|undefined} Updated block object with additional line text, or undefined if interrupted.
     * @description
     *   - Appends line text to the block element's argument if it starts with '>'.
     *   - If block is marked as interrupted, does not modify or return it.
     */
    protected function blockQuoteContinue($Line, array $Block)
    {
        if (isset($Block['interrupted']))
        {
            return;
        }

        if ($Line['text'][0] === '>' and preg_match('/^>[ ]?+(.*+)/', $Line['text'], $matches))
        {
            $Block['element']['handler']['argument'] []= $matches[1];

            return $Block;
        }

        if ( ! isset($Block['interrupted']))
        {
            $Block['element']['handler']['argument'] []= $Line['text'];

            return $Block;
        }
    }

    #
    # Rule

    /**
     * Checks if a line qualifies as a horizontal rule in markdown.
     * @example
     * $line = ['text' => '---'];
     * $result = blockRule($line);
     * // Outputs: ['element' => ['name' => 'hr']]
     * @param {Object} Line - An associative array containing a line of text to evaluate.
     * @returns {Object|null} Returns an array representing an HTML element if the line is a valid horizontal rule, or null if it's not.
     * @description
     * - The marker for a horizontal rule must be at least three consecutive identical characters.
     * - The line must consist solely of the marker and optional spaces.
     */
    protected function blockRule($Line)
    {
        $marker = $Line['text'][0];

        if (substr_count($Line['text'], $marker) >= 3 and chop($Line['text'], " $marker") === '')
        {
            $Block = array(
                'element' => array(
                    'name' => 'hr',
                ),
            );

            return $Block;
        }
    }

    #
    # Setext

    /**
     * Processes a line to determine if it can convert a paragraph block into a setext-style header.
     * @example
     * let block = blockSetextHeader({indent: 0, text: "==="}, {type: 'Paragraph', element: {name: 'p'}});
     * // block will be updated to {type: 'Paragraph', element: {name: 'h1'}}
     * @param {Object} Line - An object representing the current line with properties `indent` and `text`.
     * @param {Object} Block - A paragraph block object which may be transformed into a header.
     * @returns {Object|undefined} Returns the modified block as a header object if conditions are met; otherwise, returns undefined.
     * @description
     *   - Converts paragraph blocks directly preceded by consistent text characters into headers.
     *   - The conversion is based on whether the line's text is a series of '=' or '-' characters.
     *   - A line must not be indented more than 3 spaces to qualify for header conversion.
     *   - If the block has been interrupted, it is not eligible for conversion.
     */
    protected function blockSetextHeader($Line, array $Block = null)
    {
        if ( ! isset($Block) or $Block['type'] !== 'Paragraph' or isset($Block['interrupted']))
        {
            return;
        }

        if ($Line['indent'] < 4 and chop(chop($Line['text'], ' '), $Line['text'][0]) === '')
        {
            $Block['element']['name'] = $Line['text'][0] === '=' ? 'h1' : 'h2';

            return $Block;
        }
    }

    #
    # Markup

    /**
     * Processes a line of text for block-level HTML markup.
     * @example
     * const line = { text: '<div>Hello World</div>' };
     * const result = blockMarkup(line);
     * console.log(result); // { name: 'div', element: { rawHtml: '<div>Hello World</div>', autobreak: true } }
     * @param {Object} Line - An object containing the line of text to be processed.
     * @param {string} Line.text - The text of the line being checked for block HTML elements.
     * @returns {Object|undefined} Returns an object representing the block if a valid HTML tag is found, otherwise undefined.
     * @description 
     *   - Only processes lines that are not within a markup-escaped or safe mode context.
     *   - RegEx is used to find and verify an HTML tag in the provided line of text.
     *   - Skips processing for known text-level HTML elements.
     *   - Constructs and returns a block object with tag name and element details if criteria are met.
     */
    protected function blockMarkup($Line)
    {
        if ($this->markupEscaped or $this->safeMode)
        {
            return;
        }

        if (preg_match('/^<[\/]?+(\w*)(?:[ ]*+'.$this->regexHtmlAttribute.')*+[ ]*+(\/)?>/', $Line['text'], $matches))
        {
            $element = strtolower($matches[1]);

            if (in_array($element, $this->textLevelElements))
            {
                return;
            }

            $Block = array(
                'name' => $matches[1],
                'element' => array(
                    'rawHtml' => $Line['text'],
                    'autobreak' => true,
                ),
            );

            return $Block;
        }
    }

    protected function blockMarkupContinue($Line, array $Block)
    {
        if (isset($Block['closed']) or isset($Block['interrupted']))
        {
            return;
        }

        $Block['element']['rawHtml'] .= "\n" . $Line['body'];

        return $Block;
    }

    #
    # Reference

    /**
     * Processes a line to extract and define a reference block, containing URL and title.
     * @example
     * $line = ['text' => '[reference]: http://example.com "Example Title"'];
     * $block = blockReference($line);
     * echo json_encode($block) // Outputs: {"element":[]}
     * @param {array} $Line - Array with 'text' key holding the string to parse.
     * @returns {array} Returns an array with 'element' key which is an empty array, indicating a successful match.
     * @description
     *   - The function checks for references in markdown format to extract and store their definitions.
     *   - It converts the reference ID to lowercase for standardization.
     *   - This function updates the DefinitionData class property with the reference information.
     *   - If no matching reference is found, the function will not update the DefinitionData or return the block.
     */
    protected function blockReference($Line)
    {
        if (strpos($Line['text'], ']') !== false
            and preg_match('/^\[(.+?)\]:[ ]*+<?(\S+?)>?(?:[ ]+["\'(](.+)["\')])?[ ]*+$/', $Line['text'], $matches)
        ) {
            $id = strtolower($matches[1]);

            $Data = array(
                'url' => $matches[2],
                'title' => isset($matches[3]) ? $matches[3] : null,
            );

            $this->DefinitionData['Reference'][$id] = $Data;

            $Block = array(
                'element' => array(),
            );

            return $Block;
        }
    }

    #
    # Table

    /**
     * Parses and builds a markdown table block from a given line and block.
     * @example
     * $block = $this->blockTable(['text' => '---|---|---'], ['type' => 'Paragraph', 'element' => ['handler' => ['argument' => 'Header 1|Header 2|Header 3']]]);
     * // Returns an array structure for a table block with headers aligned based on markdown syntax.
     * @param {array} $Line - An associative array representing the current line with a 'text' key containing the separator line for the table.
     * @param {array|null} $Block - An associative array representing the current block, must be of type 'Paragraph' and not be interrupted. 
     * @returns {array|null} An associative array representing a block of parsed markdown table or null if conditions are not met.
     * @description
     *   - The function only processes lines that form a valid table header divider.
     *   - It checks for correct column alignment indicators such as ':' for left, right, or center.
     *   - The function ensures the number of header cells matches the number of alignment indicators.
     */
    protected function blockTable($Line, array $Block = null)
    {
        if ( ! isset($Block) or $Block['type'] !== 'Paragraph' or isset($Block['interrupted']))
        {
            return;
        }

        if (
            strpos($Block['element']['handler']['argument'], '|') === false
            and strpos($Line['text'], '|') === false
            and strpos($Line['text'], ':') === false
            or strpos($Block['element']['handler']['argument'], "\n") !== false
        ) {
            return;
        }

        if (chop($Line['text'], ' -:|') !== '')
        {
            return;
        }

        $alignments = array();

        $divider = $Line['text'];

        $divider = trim($divider);
        $divider = trim($divider, '|');

        $dividerCells = explode('|', $divider);

        foreach ($dividerCells as $dividerCell)
        {
            $dividerCell = trim($dividerCell);

            if ($dividerCell === '')
            {
                return;
            }

            $alignment = null;

            if ($dividerCell[0] === ':')
            {
                $alignment = 'left';
            }

            if (substr($dividerCell, - 1) === ':')
            {
                $alignment = $alignment === 'left' ? 'center' : 'right';
            }

            $alignments []= $alignment;
        }

        # ~

        $HeaderElements = array();

        $header = $Block['element']['handler']['argument'];

        $header = trim($header);
        $header = trim($header, '|');

        $headerCells = explode('|', $header);

        if (count($headerCells) !== count($alignments))
        {
            return;
        }

        foreach ($headerCells as $index => $headerCell)
        {
            $headerCell = trim($headerCell);

            $HeaderElement = array(
                'name' => 'th',
                'handler' => array(
                    'function' => 'lineElements',
                    'argument' => $headerCell,
                    'destination' => 'elements',
                )
            );

            if (isset($alignments[$index]))
            {
                $alignment = $alignments[$index];

                $HeaderElement['attributes'] = array(
                    'style' => "text-align: $alignment;",
                );
            }

            $HeaderElements []= $HeaderElement;
        }

        # ~

        $Block = array(
            'alignments' => $alignments,
            'identified' => true,
            'element' => array(
                'name' => 'table',
                'elements' => array(),
            ),
        );

        $Block['element']['elements'] []= array(
            'name' => 'thead',
        );

        $Block['element']['elements'] []= array(
            'name' => 'tbody',
            'elements' => array(),
        );

        $Block['element']['elements'][0]['elements'] []= array(
            'name' => 'tr',
            'elements' => $HeaderElements,
        );

        return $Block;
    }

    /**
     * Continues processing a table block for a markdown line.
     * @example
     * const line = { text: '| Cell 1 | Cell 2 |' };
     * const block = { alignments: ['left', 'right'], element: { elements: [null, { elements: [] }] } };
     * const result = blockTableContinue(line, block);
     * // result: { ... updated block structure ... }
     * @param {Object} Line - The current line being parsed, containing text to be added in table format.
     * @param {Array} Block - Previously processed markdown table block with current alignments and elements.
     * @returns {Array|undefined} Returns the updated block with added table row, or undefined if block is interrupted.
     * @description
     *   - The function checks whether the current line can be appended to an existing table block.
     *   - The function strips unnecessary spaces and pipe characters from the line before processing cells.
     *   - Only parses as many cells as the number of alignments provided in the existing block.
     *   - Ensures each table cell is aligned according to the alignment specified in the block's alignment configuration.
     */
    protected function blockTableContinue($Line, array $Block)
    {
        if (isset($Block['interrupted']))
        {
            return;
        }

        if (count($Block['alignments']) === 1 or $Line['text'][0] === '|' or strpos($Line['text'], '|'))
        {
            $Elements = array();

            $row = $Line['text'];

            $row = trim($row);
            $row = trim($row, '|');

            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]++`|`)++/', $row, $matches);

            $cells = array_slice($matches[0], 0, count($Block['alignments']));

            foreach ($cells as $index => $cell)
            {
                $cell = trim($cell);

                $Element = array(
                    'name' => 'td',
                    'handler' => array(
                        'function' => 'lineElements',
                        'argument' => $cell,
                        'destination' => 'elements',
                    )
                );

                if (isset($Block['alignments'][$index]))
                {
                    $Element['attributes'] = array(
                        'style' => 'text-align: ' . $Block['alignments'][$index] . ';',
                    );
                }

                $Elements []= $Element;
            }

            $Element = array(
                'name' => 'tr',
                'elements' => $Elements,
            );

            $Block['element']['elements'][1]['elements'] []= $Element;

            return $Block;
        }
    }

    #
    # ~
    #

    /**
     * Generates a paragraph element with provided text.
     *
     * @param {Object} Line - An object containing the text to be wrapped in a paragraph.
     * @param {string} Line.text - The text to be included in the paragraph element.
     * @returns {Object} An object representing a paragraph element configuration.
     * @returns {string} return.type - The type of the element, which is 'Paragraph'.
     * @returns {Object} return.element - The element details containing its name and handler.
     * @returns {string} return.element.name - The name of the element, which is 'p'.
     * @returns {Object} return.element.handler - The handler details for processing the element.
     * @returns {string} return.element.handler.function - The handler function name, which is 'lineElements'.
     * @returns {string} return.element.handler.argument - The argument to pass to the handler, which is the line text.
     * @returns {string} return.element.handler.destination - The destination for the handler result, which is 'elements'.
     * @example
     * const result = paragraph({ text: "Sample text" });
     * console.log(result);
     * // Output:
     * // {
     * //   type: 'Paragraph',
     * //   element: {
     * //     name: 'p',
     * //     handler: {
     * //       function: 'lineElements',
     * //       argument: 'Sample text',
     * //       destination: 'elements'
     * //     }
     * //   }
     * // }
     */
    protected function paragraph($Line)
    {
        return array(
            'type' => 'Paragraph',
            'element' => array(
                'name' => 'p',
                'handler' => array(
                    'function' => 'lineElements',
                    'argument' => $Line['text'],
                    'destination' => 'elements',
                ),
            ),
        );
    }

    protected function paragraphContinue($Line, array $Block)
    {
        if (isset($Block['interrupted']))
        {
            return;
        }

        $Block['element']['handler']['argument'] .= "\n".$Line['text'];

        return $Block;
    }

    #
    # Inline Elements
    #

    protected $InlineTypes = array(
        '!' => array('Image'),
        '&' => array('SpecialCharacter'),
        '*' => array('Emphasis'),
        ':' => array('Url'),
        '<' => array('UrlTag', 'EmailTag', 'Markup'),
        '[' => array('Link'),
        '_' => array('Emphasis'),
        '`' => array('Code'),
        '~' => array('Strikethrough'),
        '\\' => array('EscapeSequence'),
    );

    # ~

    protected $inlineMarkerList = '!*_&[:<`~\\';

    #
    # ~
    #

    public function line($text, $nonNestables = array())
    {
        return $this->elements($this->lineElements($text, $nonNestables));
    }

    /**
     * Processes text to extract line elements and handle non-nestable inline types.
     *
     * @example
     * const elements = lineElements("Sample text with **bold** and *italic* markers.");
     * console.log(elements);
     * // Expected output: [<element for text "Sample text with ">, <element for bold>, <element for text " and ">, <element for italic>, ...]
     *
     * @param {string} text - The input string containing text and inline markers.
     * @param {Array} [nonNestables=[]] - Array of inline types that should not be nested within the current context.
     * @returns {Array} An array of elements that represent the processed line elements from the text.
     *
     * @description
     *   - The function standardizes line breaks by converting them to '\n'.
     *   - It looks for markers in the text to determine inline types and extract them accordingly.
     *   - Ensures specific markers are not mistakenly nested if they are declared in the nonNestables array.
     *   - Adds an 'autobreak' property with default value false to each element if it isn't set already.
     */
    protected function lineElements($text, $nonNestables = array())
    {
        # standardize line breaks
        $text = str_replace(array("\r\n", "\r"), "\n", $text);

        $Elements = array();

        $nonNestables = (empty($nonNestables)
            ? array()
            : array_combine($nonNestables, $nonNestables)
        );

        # $excerpt is based on the first occurrence of a marker

        while ($excerpt = strpbrk($text, $this->inlineMarkerList))
        {
            $marker = $excerpt[0];

            $markerPosition = strlen($text) - strlen($excerpt);

            $Excerpt = array('text' => $excerpt, 'context' => $text);

            foreach ($this->InlineTypes[$marker] as $inlineType)
            {
                # check to see if the current inline type is nestable in the current context

                if (isset($nonNestables[$inlineType]))
                {
                    continue;
                }

                $Inline = $this->{"inline$inlineType"}($Excerpt);

                if ( ! isset($Inline))
                {
                    continue;
                }

                # makes sure that the inline belongs to "our" marker

                if (isset($Inline['position']) and $Inline['position'] > $markerPosition)
                {
                    continue;
                }

                # sets a default inline position

                if ( ! isset($Inline['position']))
                {
                    $Inline['position'] = $markerPosition;
                }

                # cause the new element to 'inherit' our non nestables


                $Inline['element']['nonNestables'] = isset($Inline['element']['nonNestables'])
                    ? array_merge($Inline['element']['nonNestables'], $nonNestables)
                    : $nonNestables
                ;

                # the text that comes before the inline
                $unmarkedText = substr($text, 0, $Inline['position']);

                # compile the unmarked text
                $InlineText = $this->inlineText($unmarkedText);
                $Elements[] = $InlineText['element'];

                # compile the inline
                $Elements[] = $this->extractElement($Inline);

                # remove the examined text
                $text = substr($text, $Inline['position'] + $Inline['extent']);

                continue 2;
            }

            # the marker does not belong to an inline

            $unmarkedText = substr($text, 0, $markerPosition + 1);

            $InlineText = $this->inlineText($unmarkedText);
            $Elements[] = $InlineText['element'];

            $text = substr($text, $markerPosition + 1);
        }

        $InlineText = $this->inlineText($text);
        $Elements[] = $InlineText['element'];

        foreach ($Elements as &$Element)
        {
            if ( ! isset($Element['autobreak']))
            {
                $Element['autobreak'] = false;
            }
        }

        return $Elements;
    }

    #
    # ~
    #

    /**
     * Processes inline text to identify and replace certain patterns.
     * 
     * @example
     * $text = "Hello\nWorld";
     * $result = inlineText($text);
     * var_dump($result); // array containing 'extent' and 'element' properties
     * 
     * @param {string} $text - The text to be processed for inline elements.
     * @returns {array} Returns an associative array with 'extent' indicating the length of text and 'element' containing parsed elements.
     * @description
     *   - The inline text is split into elements based on the configuration for line breaks.
     *   - When $breaksEnabled is true, splits occur at spaces followed by a newline, otherwise, breaks occur at spaces followed by a backslash or two spaces before a newline.
     *   - It uses self::pregReplaceElements to manage replacements within the input text, facilitating text transformation.
     *   - The function constructs a 'br' element and retains newline characters within the processing logic.
     */
    protected function inlineText($text)
    {
        $Inline = array(
            'extent' => strlen($text),
            'element' => array(),
        );

        $Inline['element']['elements'] = self::pregReplaceElements(
            $this->breaksEnabled ? '/[ ]*+\n/' : '/(?:[ ]*+\\\\|[ ]{2,}+)\n/',
            array(
                array('name' => 'br'),
                array('text' => "\n"),
            ),
            $text
        );

        return $Inline;
    }

    /**
     * Parses an excerpt of text to identify and format inline code surrounded by matching markers.
     * @example
     * $result = inlineCode(['text' => '`sample code`']);
     * // Output: ['extent' => 12, 'element' => ['name' => 'code', 'text' => 'sample code']];
     * @param {array} $Excerpt - An associative array containing the key 'text' with a string value of the text to be parsed for inline code.
     * @returns {array|null} Returns an associative array with 'extent' and 'element' keys if inline code is detected; otherwise, returns null.
     * @description
     *   - The function expects the code marker (e.g., backtick) to be the first character of the text.
     *   - Inline code markers must be matched pair-wise and cannot be part of the code itself.
     *   - Newlines within the marker boundaries are replaced by spaces in the parsed output.
     */
    protected function inlineCode($Excerpt)
    {
        $marker = $Excerpt['text'][0];

        if (preg_match('/^(['.$marker.']++)[ ]*+(.+?)[ ]*+(?<!['.$marker.'])\1(?!'.$marker.')/s', $Excerpt['text'], $matches))
        {
            $text = $matches[2];
            $text = preg_replace('/[ ]*+\n/', ' ', $text);

            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'code',
                    'text' => $text,
                ),
            );
        }
    }

    /**
     * Parses an excerpt of text to identify email addresses wrapped in angle brackets
     * and transforms them into HTML anchor elements.
     *
     * @example
     * const result = inlineEmailTag({ text: "<user@example.com>" });
     * console.log(result); // { extent: 20, element: { name: 'a', text: 'user@example.com', attributes: { href: 'mailto:user@example.com' } } }
     *
     * @param {Object} Excerpt - The excerpt object with a 'text' property that may contain the email address to parse.
     * @returns {Object|null} An object detailing the extent and element to be rendered, or null if no valid email is found.
     *
     * @description
     *   - Identifies valid email addresses formatted as <address>.
     *   - Supports email detection with optional "mailto:" prefix.
     *   - Generates a `mailto:` hyperlink if not already present.
     *   - Only processes when the excerpt contains a closing angle bracket.
     */
    protected function inlineEmailTag($Excerpt)
    {
        $hostnameLabel = '[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';

        $commonMarkEmail = '[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]++@'
            . $hostnameLabel . '(?:\.' . $hostnameLabel . ')*';

        if (strpos($Excerpt['text'], '>') !== false
            and preg_match("/^<((mailto:)?$commonMarkEmail)>/i", $Excerpt['text'], $matches)
        ){
            $url = $matches[1];

            if ( ! isset($matches[2]))
            {
                $url = "mailto:$url";
            }

            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'a',
                    'text' => $matches[1],
                    'attributes' => array(
                        'href' => $url,
                    ),
                ),
            );
        }
    }

    /**
     * Processes inline emphasis (e.g., bold or italic) from a given text excerpt.
     * @example
     * const result = inlineEmphasis({ text: '**bold text**' });
     * console.log(result);
     * // Outputs:
     * // {
     * //   extent: 12,
     * //   element: {
     * //     name: 'strong',
     * //     handler: {
     * //       function: 'lineElements',
     * //       argument: 'bold text',
     * //       destination: 'elements',
     * //     },
     * //   },
     * // }
     * @param {Object} Excerpt - An object containing the text from which emphasis needs to be parsed.
     * @returns {Object|undefined} Returns an object with emphasis details ('strong' or 'em') or undefined if no emphasis is found.
     * @description
     *   - The function checks for bold ('strong') or italic ('em') emphasis markers in the given text.
     *   - If both markers and text are appropriately found, it will return the formatting details including extent and element structure.
     *   - The function uses regular expressions stored in `StrongRegex` and `EmRegex` properties to identify the correct type of emphasis.
     *   - Returns undefined if no valid emphasis is detected at the beginning of the text.
     */
    protected function inlineEmphasis($Excerpt)
    {
        if ( ! isset($Excerpt['text'][1]))
        {
            return;
        }

        $marker = $Excerpt['text'][0];

        if ($Excerpt['text'][1] === $marker and preg_match($this->StrongRegex[$marker], $Excerpt['text'], $matches))
        {
            $emphasis = 'strong';
        }
        elseif (preg_match($this->EmRegex[$marker], $Excerpt['text'], $matches))
        {
            $emphasis = 'em';
        }
        else
        {
            return;
        }

        return array(
            'extent' => strlen($matches[0]),
            'element' => array(
                'name' => $emphasis,
                'handler' => array(
                    'function' => 'lineElements',
                    'argument' => $matches[1],
                    'destination' => 'elements',
                )
            ),
        );
    }

    protected function inlineEscapeSequence($Excerpt)
    {
        if (isset($Excerpt['text'][1]) and in_array($Excerpt['text'][1], $this->specialCharacters))
        {
            return array(
                'element' => array('rawHtml' => $Excerpt['text'][1]),
                'extent' => 2,
            );
        }
    }

    /**
     * Processes an excerpt of text to convert inline images in markdown format to HTML.
     * @param {Object} Excerpt - An object containing the excerpt of text and other metadata.
     * @param {string} Excerpt.text - A segment of text possibly containing a markdown image link.
     * @returns {Object|null} An object representing the HTML image element or null if processing is not possible.
     * @example
     * const Excerpt = { text: "![alt text](http://example.com/image.jpg)" };
     * const result = inlineImage(Excerpt);
     * // result will be an object containing HTML img element details
     * // example output: { extent: 29, element: { name: "img", attributes: { src: "http://example.com/image.jpg", alt: "alt text" }, autobreak: true } }
     * @description
     *   - Ensures that the markdown image syntax starts with an exclamation mark followed by an opening bracket.
     *   - Converts markdown-styled image links to HTML <img> tags.
     *   - Incorporates all attributes from the markdown link except the href, which is used as the src.
     */
    protected function inlineImage($Excerpt)
    {
        if ( ! isset($Excerpt['text'][1]) or $Excerpt['text'][1] !== '[')
        {
            return;
        }

        $Excerpt['text']= substr($Excerpt['text'], 1);

        $Link = $this->inlineLink($Excerpt);

        if ($Link === null)
        {
            return;
        }

        $Inline = array(
            'extent' => $Link['extent'] + 1,
            'element' => array(
                'name' => 'img',
                'attributes' => array(
                    'src' => $Link['element']['attributes']['href'],
                    'alt' => $Link['element']['handler']['argument'],
                ),
                'autobreak' => true,
            ),
        );

        $Inline['element']['attributes'] += $Link['element']['attributes'];

        unset($Inline['element']['attributes']['href']);

        return $Inline;
    }

    /**
     * Processes an excerpt of text to identify and create an inline link element.
     * The function examines the structure of the text and extracts necessary attributes for the link such as href and title.
     *
     * @example
     * const excerpt = { text: "[example](http://example.com 'Example Title')" };
     * const result = inlineLink(excerpt);
     * console.log(result); // { extent: 42, element: { name: 'a', attributes: { href: 'http://example.com', title: 'Example Title' }, ... } }
     *
     * @param {Object} Excerpt - An object containing the text to be parsed for an inline link.
     * @param {string} Excerpt.text - The text content in which to find the inline link specifications.
     * @returns {Object|undefined} Returns an object containing the extent of parsing and the link element details, or undefined if parsing fails.
     * @description
     *   - Parses markdown-like link structures from the given text excerpt.
     *   - Supports both inline links (e.g., `[link](url)`) and reference-style links.
     *   - Sets element attributes including href and optionally title based on the text content.
     *   - Leverages external definition data for reference-style links if needed.
     */
    protected function inlineLink($Excerpt)
    {
        $Element = array(
            'name' => 'a',
            'handler' => array(
                'function' => 'lineElements',
                'argument' => null,
                'destination' => 'elements',
            ),
            'nonNestables' => array('Url', 'Link'),
            'attributes' => array(
                'href' => null,
                'title' => null,
            ),
        );

        $extent = 0;

        $remainder = $Excerpt['text'];

        if (preg_match('/\[((?:[^][]++|(?R))*+)\]/', $remainder, $matches))
        {
            $Element['handler']['argument'] = $matches[1];

            $extent += strlen($matches[0]);

            $remainder = substr($remainder, $extent);
        }
        else
        {
            return;
        }

        if (preg_match('/^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?:[ ]+("[^"]*+"|\'[^\']*+\'))?\s*+[)]/', $remainder, $matches))
        {
            $Element['attributes']['href'] = $matches[1];

            if (isset($matches[2]))
            {
                $Element['attributes']['title'] = substr($matches[2], 1, - 1);
            }

            $extent += strlen($matches[0]);
        }
        else
        {
            if (preg_match('/^\s*\[(.*?)\]/', $remainder, $matches))
            {
                $definition = strlen($matches[1]) ? $matches[1] : $Element['handler']['argument'];
                $definition = strtolower($definition);

                $extent += strlen($matches[0]);
            }
            else
            {
                $definition = strtolower($Element['handler']['argument']);
            }

            if ( ! isset($this->DefinitionData['Reference'][$definition]))
            {
                return;
            }

            $Definition = $this->DefinitionData['Reference'][$definition];

            $Element['attributes']['href'] = $Definition['url'];
            $Element['attributes']['title'] = $Definition['title'];
        }

        return array(
            'extent' => $extent,
            'element' => $Element,
        );
    }

    /**
     * Processes inline HTML markup in markdown text.
     * @example
     * const result = inlineMarkup({ text: '<p>Sample</p>' });
     * console.log(result); // { element: { rawHtml: '<p>' }, extent: 3 }
     * @param {Object} Excerpt - An object containing the text to be parsed.
     * @param {string} Excerpt.text - The input text containing potential HTML markup.
     * @returns {Object|undefined} Returns an object containing the raw HTML and its extent if a valid HTML tag is found, otherwise returns undefined.
     * @description
     *   - Handles self-closing and comment HTML tags in markdown text.
     *   - Protects against unsafe HTML by checking the state of markupEscaped and safeMode properties.
     *   - Utilizes regular expressions to identify valid HTML structures.
     *   - Ignores invalid tags that do not start with appropriate identifiers.
     */
    protected function inlineMarkup($Excerpt)
    {
        if ($this->markupEscaped or $this->safeMode or strpos($Excerpt['text'], '>') === false)
        {
            return;
        }

        if ($Excerpt['text'][1] === '/' and preg_match('/^<\/\w[\w-]*+[ ]*+>/s', $Excerpt['text'], $matches))
        {
            return array(
                'element' => array('rawHtml' => $matches[0]),
                'extent' => strlen($matches[0]),
            );
        }

        if ($Excerpt['text'][1] === '!' and preg_match('/^<!---?[^>-](?:-?+[^-])*-->/s', $Excerpt['text'], $matches))
        {
            return array(
                'element' => array('rawHtml' => $matches[0]),
                'extent' => strlen($matches[0]),
            );
        }

        if ($Excerpt['text'][1] !== ' ' and preg_match('/^<\w[\w-]*+(?:[ ]*+'.$this->regexHtmlAttribute.')*+[ ]*+\/?>/s', $Excerpt['text'], $matches))
        {
            return array(
                'element' => array('rawHtml' => $matches[0]),
                'extent' => strlen($matches[0]),
            );
        }
    }

    /**
     * Processes an excerpt of text and identifies a special HTML entity if present.
     * @example
     * const result = inlineSpecialCharacter({ text: '&amp; some text' });
     * console.log(result); // { element: { rawHtml: '&amp;' }, extent: 5 }
     * @param {Object} Excerpt - An object containing text to be parsed.
     * @returns {Object|undefined} Returns an object with the parsed HTML entity and its length if a valid entity is found; otherwise returns undefined.
     * @description
     *   - The function checks whether the second character in Excerpt.text is not a space and a semicolon is present to ensure an HTML entity structure.
     *   - Utilizes a regular expression to match HTML entities starting with '&' followed by numbers or letters ending in ';'.
     *   - Returns an object containing 'rawHtml' with the matched entity and 'extent' indicating the length of matched string.
     */
    protected function inlineSpecialCharacter($Excerpt)
    {
        if (substr($Excerpt['text'], 1, 1) !== ' ' and strpos($Excerpt['text'], ';') !== false
            and preg_match('/^&(#?+[0-9a-zA-Z]++);/', $Excerpt['text'], $matches)
        ) {
            return array(
                'element' => array('rawHtml' => '&' . $matches[1] . ';'),
                'extent' => strlen($matches[0]),
            );
        }

        return;
    }

    /**
     * Processes an excerpt to identify and format strikethrough text using double tildes.
     * @example
     * const parsed = inlineStrikethrough({text: '~~strikethrough~~ example'});
     * console.log(parsed); // Outputs the formatted strikethrough element.
     * @param {Object} Excerpt - An object containing the text to be parsed.
     * @param {string} Excerpt.text - The text string that may contain strikethrough indicators.
     * @returns {Object|undefined} Returns an object with extent and element properties if a strikethrough is found,
     * or undefined if not.
     * @description
     *   - Checks for the string starting with double tildes and non-space character.
     *   - Returns an element configuration for rendering the strikethrough.
     *   - Utilizes a regular expression to ensure strikethrough integrity with no leading/trailing spaces inside tildes.
     */
    protected function inlineStrikethrough($Excerpt)
    {
        if ( ! isset($Excerpt['text'][1]))
        {
            return;
        }

        if ($Excerpt['text'][1] === '~' and preg_match('/^~~(?=\S)(.+?)(?<=\S)~~/', $Excerpt['text'], $matches))
        {
            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'del',
                    'handler' => array(
                        'function' => 'lineElements',
                        'argument' => $matches[1],
                        'destination' => 'elements',
                    )
                ),
            );
        }
    }

    /**
     * Processes an excerpt to find and convert URLs into link elements.
     * @example
     * const excerpt = { text: "http://example.com", context: "Check this out http://example.com" };
     * const result = inlineUrl(excerpt);
     * console.log(result); 
     * // {
     * //   extent: 18,
     * //   position: 14,
     * //   element: {
     * //     name: 'a',
     * //     text: 'http://example.com',
     * //     attributes: {
     * //       href: 'http://example.com'
     * //     }
     * //   }
     * // }
     * @param {Object} Excerpt - An object containing the text and context of potential URLs.
     * @param {string} Excerpt.text - The text to be checked for URLs.
     * @param {string} Excerpt.context - The surrounding text or context that might contain URLs.
     * @returns {Object|undefined} Returns an object representing the link element if a URL is found, otherwise undefined.
     * @description
     *   - Ensures URLs prefixed with http are converted into clickable links.
     *   - Only processes URLs that appear within a context suggesting they're actual links.
     *   - Appends necessary HTML attributes to the link for proper rendering.
     */
    protected function inlineUrl($Excerpt)
    {
        if ($this->urlsLinked !== true or ! isset($Excerpt['text'][2]) or $Excerpt['text'][2] !== '/')
        {
            return;
        }

        if (strpos($Excerpt['context'], 'http') !== false
            and preg_match('/\bhttps?+:[\/]{2}[^\s<]+\b\/*+/ui', $Excerpt['context'], $matches, PREG_OFFSET_CAPTURE)
        ) {
            $url = $matches[0][0];

            $Inline = array(
                'extent' => strlen($matches[0][0]),
                'position' => $matches[0][1],
                'element' => array(
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => array(
                        'href' => $url,
                    ),
                ),
            );

            return $Inline;
        }
    }

    /**
     * Parses a given excerpt to find and convert an inline URL tag into an anchor (<a>) element.
     * @example
     * let excerpt = { text: '<http://example.com>' };
     * let result = inlineUrlTag(excerpt);
     * // result = {
     * //   extent: 18,
     * //   element: {
     * //     name: 'a',
     * //     text: 'http://example.com',
     * //     attributes: {
     * //       href: 'http://example.com',
     * //     }
     * //   }
     * // }
     * @param {Object} Excerpt - An object containing a 'text' property with a string possibly containing a URL.
     * @returns {Object|undefined} 
     * Returns an object with 'extent' and 'element' properties if a URL is matched and converted; 
     * returns `undefined` if no matching URL is found.
     * @description
     *   - The function specifically looks for text in the format of a URL enclosed in angle brackets.
     *   - It checks for a protocol followed by '://' to determine a URL.
     */
    protected function inlineUrlTag($Excerpt)
    {
        if (strpos($Excerpt['text'], '>') !== false and preg_match('/^<(\w++:\/{2}[^ >]++)>/i', $Excerpt['text'], $matches))
        {
            $url = $matches[1];

            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => array(
                        'href' => $url,
                    ),
                ),
            );
        }
    }

    # ~

    protected function unmarkedText($text)
    {
        $Inline = $this->inlineText($text);
        return $this->element($Inline['element']);
    }

    #
    # Handlers
    #

    /**
     * Handles the transformation of an element using specified handlers.
     * @example
     * const result = handle({
     *   handler: 'someFunction',
     *   text: 'sample text'
     * });
     * console.log(result); // Sample output with processed element
     * @param {Object} Element - The element containing transformation handlers and other properties.
     * @returns {Object} The transformed element with processed content.
     * @description
     *   - If the handler is a string, it performs a direct transformation with 'text'.
     *   - Supports nested handlers unless restricted by 'nonNestables'.
     *   - Dynamically assigns result to a specified property based on the handler's configuration.
     */
    protected function handle(array $Element)
    {
        if (isset($Element['handler']))
        {
            if (!isset($Element['nonNestables']))
            {
                $Element['nonNestables'] = array();
            }

            if (is_string($Element['handler']))
            {
                $function = $Element['handler'];
                $argument = $Element['text'];
                unset($Element['text']);
                $destination = 'rawHtml';
            }
            else
            {
                $function = $Element['handler']['function'];
                $argument = $Element['handler']['argument'];
                $destination = $Element['handler']['destination'];
            }

            $Element[$destination] = $this->{$function}($argument, $Element['nonNestables']);

            if ($destination === 'handler')
            {
                $Element = $this->handle($Element);
            }

            unset($Element['handler']);
        }

        return $Element;
    }

    protected function handleElementRecursive(array $Element)
    {
        return $this->elementApplyRecursive(array($this, 'handle'), $Element);
    }

    protected function handleElementsRecursive(array $Elements)
    {
        return $this->elementsApplyRecursive(array($this, 'handle'), $Elements);
    }

    /**
     * Recursively applies a given closure to an element and its nested elements.
     *
     * @param {Function} closure - The closure to apply to each element.
     * @param {Array} Element - The element array to which the closure will be applied.
     * @returns {Array} The modified element array after applying the closure recursively.
     *
     * @example
     * const newElement = elementApplyRecursive(myClosureFunction, sampleElement);
     * console.log(newElement); // Output: modified element structure.
     *
     * @description
     *   - This function handles both singular elements and arrays of elements.
     *   - It modifies the input element directly and returns the transformed structure.
     *   - Ensures that the closure is applied to each level of nested elements.
     */
    protected function elementApplyRecursive($closure, array $Element)
    {
        $Element = call_user_func($closure, $Element);

        if (isset($Element['elements']))
        {
            $Element['elements'] = $this->elementsApplyRecursive($closure, $Element['elements']);
        }
        elseif (isset($Element['element']))
        {
            $Element['element'] = $this->elementApplyRecursive($closure, $Element['element']);
        }

        return $Element;
    }

    /**
     * Applies a given closure function to an element in a recursive depth-first manner.
     * 
     * @param {Function} closure - The closure function to apply to each element.
     * @param {Object} Element - The element object to which the closure will be applied. Example structure: { element: { ... } }.
     * @returns {Object} The modified element after applying the closure function.
     * @example
     * const exampleClosure = (element) => {
     *     // Modify element
     *     element.modified = true;
     *     return element;
     * };
     * const sampleElement = { element: { key: 'value' } };
     * const result = elementApplyRecursiveDepthFirst(exampleClosure, sampleElement);
     * console.log(result); // { element: { key: 'value', modified: true } }
     * @description
     *   - If the element contains sub-elements (keyed by 'elements' or 'element'), the closure is applied recursively.
     *   - This function does not modify elements in place but returns anew modified structure.
     *   - Assumes closure always returns a valid element structure after modification.
     */
    protected function elementApplyRecursiveDepthFirst($closure, array $Element)
    {
        if (isset($Element['elements']))
        {
            $Element['elements'] = $this->elementsApplyRecursiveDepthFirst($closure, $Element['elements']);
        }
        elseif (isset($Element['element']))
        {
            $Element['element'] = $this->elementsApplyRecursiveDepthFirst($closure, $Element['element']);
        }

        $Element = call_user_func($closure, $Element);

        return $Element;
    }

    protected function elementsApplyRecursive($closure, array $Elements)
    {
        foreach ($Elements as &$Element)
        {
            $Element = $this->elementApplyRecursive($closure, $Element);
        }

        return $Elements;
    }

    protected function elementsApplyRecursiveDepthFirst($closure, array $Elements)
    {
        foreach ($Elements as &$Element)
        {
            $Element = $this->elementApplyRecursiveDepthFirst($closure, $Element);
        }

        return $Elements;
    }

    /**
     * Processes and converts an array representation of an HTML element into its HTML string representation.
     * @example
     * const elementData = {
     *   name: 'a',
     *   attributes: { href: 'https://example.com', target: '_blank' },
     *   text: 'Click here'
     * };
     * const markup = element(elementData);
     * console.log(markup); // Outputs: <a href="https://example.com" target="_blank">Click here</a>;
     * @param {Object} Element - An associative array containing the structure of the HTML element.
     * @param {string} Element.name - The HTML tag name, e.g., 'div', 'span', 'a'.
     * @param {Object} [Element.attributes] - Key-value pairs for attributes of the element.
     * @param {string|null|undefined} [Element.text] - Text or raw HTML to be wrapped by the element tag.
     * @param {Object} [Element.element] - A nested element in the same format to be processed recursively.
     * @param {Array} [Element.elements] - An array of nested elements in the same format to be processed recursively.
     * @param {boolean} [Element.allowRawHtmlInSafeMode] - Flags whether raw HTML is permissible in safe mode.
     * @returns {string} The HTML markup generated from the provided element data.
     * @description
     *   - Sanitizes elements based on safeMode settings to prevent XSS.
     *   - Handles both self-closing tags and tags with content.
     *   - Recursively processes nested elements and elements array.
     *   - Allows conditional rendering of raw HTML based on configuration.
     */
    protected function element(array $Element)
    {
        if ($this->safeMode)
        {
            $Element = $this->sanitiseElement($Element);
        }

        # identity map if element has no handler
        $Element = $this->handle($Element);

        $hasName = isset($Element['name']);

        $markup = '';

        if ($hasName)
        {
            $markup .= '<' . $Element['name'];

            if (isset($Element['attributes']))
            {
                foreach ($Element['attributes'] as $name => $value)
                {
                    if ($value === null)
                    {
                        continue;
                    }

                    $markup .= " $name=\"".self::escape($value).'"';
                }
            }
        }

        $permitRawHtml = false;

        if (isset($Element['text']))
        {
            $text = $Element['text'];
        }
        // very strongly consider an alternative if you're writing an
        // extension
        elseif (isset($Element['rawHtml']))
        {
            $text = $Element['rawHtml'];

            $allowRawHtmlInSafeMode = isset($Element['allowRawHtmlInSafeMode']) && $Element['allowRawHtmlInSafeMode'];
            $permitRawHtml = !$this->safeMode || $allowRawHtmlInSafeMode;
        }

        $hasContent = isset($text) || isset($Element['element']) || isset($Element['elements']);

        if ($hasContent)
        {
            $markup .= $hasName ? '>' : '';

            if (isset($Element['elements']))
            {
                $markup .= $this->elements($Element['elements']);
            }
            elseif (isset($Element['element']))
            {
                $markup .= $this->element($Element['element']);
            }
            else
            {
                if (!$permitRawHtml)
                {
                    $markup .= self::escape($text, true);
                }
                else
                {
                    $markup .= $text;
                }
            }

            $markup .= $hasName ? '</' . $Element['name'] . '>' : '';
        }
        elseif ($hasName)
        {
            $markup .= ' />';
        }

        return $markup;
    }

    /**
     * Generates and returns a markup string assembled from the given array of elements.
     * @example
     * const elementsArray = [
     *   {name: 'div', autobreak: true}, 
     *   {name: 'span', autobreak: false}
     * ];
     * const result = elements(elementsArray);
     * console.log(result); // Outputs formatted markup string with optional line breaks.
     * @param {Object[]} Elements - An array of elements, each represented as an object with optional 'name' and 'autobreak' properties.
     * @returns {string} A markup string composed from the elements, with line breaks based on 'autobreak' settings.
     * @description
     *   - Auto-line-breaks can be controlled per element through the 'autobreak' property.
     *   - Consecutive elements may be joined without a line break if 'autobreak' is false.
     */
    protected function elements(array $Elements)
    {
        $markup = '';

        $autoBreak = true;

        foreach ($Elements as $Element)
        {
            if (empty($Element))
            {
                continue;
            }

            $autoBreakNext = (isset($Element['autobreak'])
                ? $Element['autobreak'] : isset($Element['name'])
            );
            // (autobreak === false) covers both sides of an element
            $autoBreak = !$autoBreak ? $autoBreak : $autoBreakNext;

            $markup .= ($autoBreak ? "\n" : '') . $this->element($Element);
            $autoBreak = $autoBreakNext;
        }

        $markup .= $autoBreak ? "\n" : '';

        return $markup;
    }

    # ~

    /**
     * Processes an array of lines and extracts elements for list items.
     * @example
     * $lines = ['This is a line.', '', 'Another line.'];
     * $result = $this->li($lines);
     * // Output: processed array of elements
     * @param {Array} lines - An array of strings representing lines.
     * @returns {Array} Returns an array of elements derived from the lines.
     * @description
     *   - This function is a protected method used to parse list items.
     *   - It modifies the element properties when certain conditions are met.
     *   - The function relies on the linesElements method to extract elements.
     *   - Primarily processes paragraphs within list items.
     */
    protected function li($lines)
    {
        $Elements = $this->linesElements($lines);

        if ( ! in_array('', $lines)
            and isset($Elements[0]) and isset($Elements[0]['name'])
            and $Elements[0]['name'] === 'p'
        ) {
            unset($Elements[0]['name']);
        }

        return $Elements;
    }

    #
    # AST Convenience
    #

    /**
     * Replace occurrences $regexp with $Elements in $text. Return an array of
     * elements representing the replacement.
     */
    protected static function pregReplaceElements($regexp, $Elements, $text)
    {
        $newElements = array();

        while (preg_match($regexp, $text, $matches, PREG_OFFSET_CAPTURE))
        {
            $offset = $matches[0][1];
            $before = substr($text, 0, $offset);
            $after = substr($text, $offset + strlen($matches[0][0]));

            $newElements[] = array('text' => $before);

            foreach ($Elements as $Element)
            {
                $newElements[] = $Element;
            }

            $text = $after;
        }

        $newElements[] = array('text' => $text);

        return $newElements;
    }

    #
    # Deprecated Methods
    #

    function parse($text)
    {
        $markup = $this->text($text);

        return $markup;
    }

    /**
     * Sanitises an HTML element by removing unsafe attributes and filtering URLs.
     * @param {Object} Element - The element object containing attributes to be sanitised.
     * @param {string} Element.name - The tag name of the HTML element (e.g., 'a', 'img').
     * @param {Object} Element.attributes - An object containing the element's attributes.
     * @returns {Object} Returns a sanitised element with potentially unsafe attributes removed.
     * @example
     * const sanitisedElement = sanitiseElement({
     *   name: 'img',
     *   attributes: {
     *     src: 'http://example.com/image.jpg',
     *     onload: 'alert("Hello");'
     *   }
     * });
     * // Output: { name: 'img', attributes: { src: 'http://example.com/image.jpg' } }
     * @description
     * - The function removes any attributes that do not match the `goodAttribute` regex pattern.
     * - Attributes starting with 'on', indicating event handlers, are removed for security reasons.
     * - Applies specific URL filtering on certain element types using `filterUnsafeUrlInAttribute`.
     */
    protected function sanitiseElement(array $Element)
    {
        static $goodAttribute = '/^[a-zA-Z0-9][a-zA-Z0-9-_]*+$/';
        static $safeUrlNameToAtt  = array(
            'a'   => 'href',
            'img' => 'src',
        );

        if ( ! isset($Element['name']))
        {
            unset($Element['attributes']);
            return $Element;
        }

        if (isset($safeUrlNameToAtt[$Element['name']]))
        {
            $Element = $this->filterUnsafeUrlInAttribute($Element, $safeUrlNameToAtt[$Element['name']]);
        }

        if ( ! empty($Element['attributes']))
        {
            foreach ($Element['attributes'] as $att => $val)
            {
                # filter out badly parsed attribute
                if ( ! preg_match($goodAttribute, $att))
                {
                    unset($Element['attributes'][$att]);
                }
                # dump onevent attribute
                elseif (self::striAtStart($att, 'on'))
                {
                    unset($Element['attributes'][$att]);
                }
            }
        }

        return $Element;
    }

    /**
     * Filters unsafe URLs from a given attribute in an element.
     * @example
     * $element = ['attributes' => ['href' => 'javascript:alert(1)']];
     * $filteredElement = filterUnsafeUrlInAttribute($element, 'href');
     * // The 'javascript:' scheme is replaced by 'javascript%3A' in the 'href' attribute.
     * @param {array} $Element - The element containing attributes to be filtered.
     * @param {string} $attribute - The name of the attribute to be checked and potentially filtered.
     * @returns {array} The updated element with unsafe URLs filtered in the specified attribute.
     * @description
     *   - The function checks URLs in attributes against a whitelist of safe schemes.
     *   - If the URL starts with a safe scheme, no filtering is applied.
     *   - URLs starting with unsafe schemes have colons replaced with '%3A'.
     */
    protected function filterUnsafeUrlInAttribute(array $Element, $attribute)
    {
        foreach ($this->safeLinksWhitelist as $scheme)
        {
            if (self::striAtStart($Element['attributes'][$attribute], $scheme))
            {
                return $Element;
            }
        }

        $Element['attributes'][$attribute] = str_replace(':', '%3A', $Element['attributes'][$attribute]);

        return $Element;
    }

    #
    # Static Methods
    #

    protected static function escape($text, $allowQuotes = false)
    {
        return htmlspecialchars($text, $allowQuotes ? ENT_NOQUOTES : ENT_QUOTES, 'UTF-8');
    }

    /**
     * Checks if a string begins with a specified substring, case-insensitively.
     * @example
     * const result = striAtStart("HelloWorld", "hello");
     * console.log(result); // Output: true
     * @param {string} string - The main string to check within.
     * @param {string} needle - The substring to look for at the start.
     * @returns {boolean} True if the main string starts with the substring case-insensitively, false otherwise.
     * @description
     *   - The function performs a case-insensitive comparison.
     *   - If the substring length is greater than the main string, returns false immediately.
     *   - Utilizes `substr` to extract a portion of the string for comparison.
     */
    protected static function striAtStart($string, $needle)
    {
        $len = strlen($needle);

        if ($len > strlen($string))
        {
            return false;
        }
        else
        {
            return strtolower(substr($string, 0, $len)) === strtolower($needle);
        }
    }

    /**
     * Returns an instance of the current class corresponding to the provided name.
     * @example
     * $parsedown = Parsedown::instance('markdownParser');
     * echo get_class($parsedown); // Outputs: "Parsedown"
     * @param {string} name - The name of the instance to retrieve, default is 'default'.
     * @returns {object} An instance of the current class.
     * @description
     *   - The method creates a new instance if it doesn't exist for the given name.
     *   - Instances are stored statically and reused on subsequent calls with the same name.
     *   - Facilitates the singleton design pattern with named instances.
     */
    static function instance($name = 'default')
    {
        if (isset(self::$instances[$name]))
        {
            return self::$instances[$name];
        }

        $instance = new static();

        self::$instances[$name] = $instance;

        return $instance;
    }

    private static $instances = array();

    #
    # Fields
    #

    protected $DefinitionData;

    #
    # Read-Only

    protected $specialCharacters = array(
        '\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '>', '#', '+', '-', '.', '!', '|', '~'
    );

    protected $StrongRegex = array(
        '*' => '/^[*]{2}((?:\\\\\*|[^*]|[*][^*]*+[*])+?)[*]{2}(?![*])/s',
        '_' => '/^__((?:\\\\_|[^_]|_[^_]*+_)+?)__(?!_)/us',
    );

    protected $EmRegex = array(
        '*' => '/^[*]((?:\\\\\*|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s',
        '_' => '/^_((?:\\\\_|[^_]|__[^_]*__)+?)_(?!_)\b/us',
    );

    protected $regexHtmlAttribute = '[a-zA-Z_:][\w:.-]*+(?:\s*+=\s*+(?:[^"\'=<>`\s]+|"[^"]*+"|\'[^\']*+\'))?+';

    protected $voidElements = array(
        'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source',
    );

    protected $textLevelElements = array(
        'a', 'br', 'bdo', 'abbr', 'blink', 'nextid', 'acronym', 'basefont',
        'b', 'em', 'big', 'cite', 'small', 'spacer', 'listing',
        'i', 'rp', 'del', 'code',          'strike', 'marquee',
        'q', 'rt', 'ins', 'font',          'strong',
        's', 'tt', 'kbd', 'mark',
        'u', 'xm', 'sub', 'nobr',
                   'sup', 'ruby',
                   'var', 'span',
                   'wbr', 'time',
    );
}
