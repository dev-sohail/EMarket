<?php

namespace App\Http\Requests\Admin;

use App\Models\Tag;

/**
 * Validates the tag edit form.
 *
 * The rules are the store rules unchanged; the only difference is that the slug
 * uniqueness check excludes the tag being edited, so saving a tag without
 * renaming it does not report a conflict with itself.
 */
class UpdateTagRequest extends StoreTagRequest
{
    /**
     * The key of the tag being updated, or null if the route did not resolve one.
     *
     * Read from the route rather than a field, because the form posts no id and
     * a crafted request could not be trusted to post the right one.
     */
    protected function ignoringTagId(): ?string
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag ? $tag->getKey() : null;
    }
}
