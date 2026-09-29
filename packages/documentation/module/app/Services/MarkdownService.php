<?php

namespace Modules\Documentation\Services;

use Illuminate\Validation\ValidationException;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image as MarkdownImage;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use Modules\Documentation\Models\Image;

class MarkdownService
{
    /** GitHub alert types, rendered as VitePress-like callouts. */
    public const CALLOUTS = ['note', 'tip', 'important', 'warning', 'caution'];

    public const CALLOUT_LABELS = [
        'fr' => ['note' => 'Remarque', 'tip' => 'Astuce', 'important' => 'Important', 'warning' => 'Attention', 'caution' => 'Danger'],
        'en' => ['note' => 'Note', 'tip' => 'Tip', 'important' => 'Important', 'warning' => 'Warning', 'caution' => 'Caution'],
    ];

    private string $locale = 'fr';

    private function converter(callable $visit): GithubFlavoredMarkdownConverter
    {
        $converter = new GithubFlavoredMarkdownConverter(['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 50, 'heading_permalink' => ['id_prefix' => '', 'fragment_prefix' => '', 'apply_id_to_heading' => true, 'insert' => 'none']]);
        $converter->getEnvironment()->addExtension(new HeadingPermalinkExtension);
        $converter->getEnvironment()->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use ($visit) {
            $quotes = [];
            $codes = [];
            $walker = $event->getDocument()->walker();
            while ($node = $walker->next()) {
                $current = $node->getNode();
                if (! $node->isEntering()) {
                    continue;
                }
                if ($current instanceof MarkdownImage && preg_match('/^doc-image:(\d+)$/', $current->getUrl(), $match)) {
                    $visit($current, (int) $match[1]);
                } elseif ($current instanceof BlockQuote) {
                    $quotes[] = $current;
                } elseif ($current instanceof FencedCode) {
                    $codes[] = $current;
                }
            }
            // Mutate after walking so the traversal never sees detached nodes.
            array_walk($quotes, fn (BlockQuote $quote) => $this->callout($quote));
            array_walk($codes, fn (FencedCode $code) => $this->codeMeta($code));
        });

        return $converter;
    }

    /** Turns "> [!TIP] Optional title" into a styled callout block. */
    private function callout(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();
        $marker = $paragraph instanceof Paragraph ? $paragraph->firstChild() : null;
        if (! $marker instanceof Text || ! preg_match('/^\[!('.implode('|', self::CALLOUTS).')\][ \t]*(.*)$/i', $marker->getLiteral(), $match)) {
            return;
        }
        $type = strtolower($match[1]);
        $title = new Paragraph;
        $title->data->set('attributes/class', 'cms-callout-title');
        // Everything up to the first line break is the title, inline formatting included.
        $inline = [];
        for ($node = $marker->next(); $node && ! $node instanceof Newline; $node = $node->next()) {
            $inline[] = $node;
        }
        $custom = ltrim($match[2]);
        if ($custom !== '' || $inline) {
            if ($custom !== '') {
                $title->appendChild(new Text($custom));
            }
            foreach ($inline as $node) {
                $title->appendChild($node);
            }
        } else {
            $title->appendChild(new Text((self::CALLOUT_LABELS[$this->locale] ?? self::CALLOUT_LABELS['fr'])[$type]));
        }
        $next = $marker->next();
        $marker->detach();
        if ($next instanceof Newline) {
            $next->detach();
        }
        if (! $paragraph->hasChildren()) {
            $paragraph->detach();
        }
        $quote->prependChild($title);
        $quote->data->set('attributes/class', 'cms-callout cms-callout-'.$type);
    }

    /** Supports "```php [app/Models/User.php] {2,4-6}" titles and highlighted lines. */
    private function codeMeta(FencedCode $code): void
    {
        $info = trim($code->getInfo() ?? '');
        if (preg_match('/\[([^\]]{1,120})\]/', $info, $title)) {
            $code->data->set('attributes/data-title', trim($title[1]));
        }
        if (preg_match('/\{([\d,\s-]{1,80})\}/', $info, $lines)) {
            $code->data->set('attributes/data-lines', preg_replace('/\s+/', '', $lines[1]));
        }
    }

    public function imageIds(string $markdown): array
    {
        $ids = [];
        $this->converter(function (MarkdownImage $image, int $id) use (&$ids) {
            $ids[] = $id;
        })->convert($markdown);

        return array_values(array_unique($ids));
    }

    public function render(string $markdown, array $images, string $locale = 'fr'): string
    {
        $this->locale = $locale;

        return (string) $this->converter(fn (MarkdownImage $image, int $id) => $image->setUrl($images[$id] ?? '#missing-image'))->convert($markdown);
    }

    public function images(string $markdown, int $collectionId): array
    {
        $ids = $this->imageIds($markdown);
        $images = Image::where('collection_id', $collectionId)->whereIn('id', $ids)->get();
        if ($images->count() !== count($ids)) {
            throw ValidationException::withMessages(['markdown' => 'Une image est introuvable ou appartient à une autre documentation.']);
        }

        return $images->mapWithKeys(fn ($image) => [$image->id => '/api/v1/documentation/images/'.$image->id.'/file'])->all();
    }
}
