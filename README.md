# Search Improvements

A collection of improvements to XF's enhanced search and XenForo's default MySQL search.

- Allow `*` (or empty search string) to return results, for MySQL and XFES
- range_query search DSL
  - allows arbitrary range queries for numerical data
- Allow users to select the default search order independent for the forum wide setting.
  - Re-adds the global option for the default search type
- Display search terms on the search results page 
- Add "Search only X" search criteria to individual handler pages, where X is thread/conversation/ticket/ect instead of searching thread/post etc.
   - Makes general search a true subset of member search

Elasticsearch only features:
- Add ability to push "can view threads/tickets by other" permission(s) into ElasticSearch query, reducing php-side culling of matching content.
  This improves searching forums/tickets where the user lacks these permissions.

  This is gated behind the option `Push "View X by others" check into XFES'`, as it requires a full reindex. (Default disabled)

  Supports the following add-ons:
    - View Sticky Threads (free) add-on.
    - Collaborative Threads (paid) add-on.
    - NixFifty's Tickets (paid) add-on.
For best results, use ElasticSearch Essentials add-on, as it simplifies this permission constraint compared to stock XenForo
- Per content type weighting
- Adds Elasticsearch information to the AdminCP home screen.
- Adds a debug option to log the search DSL queries to error log for troubleshooting
- Option to extend search syntax to permit;
    - `+` signifies AND operation
    - `|` signifies OR operation
    - `-` negates a single token
    - `"` wraps a number of tokens to signify a phrase for searching
    - `*` at the end of a term signifies a prefix query
    - `(` and `)` signify precedence
    - `~N` after a word signifies edit distance (fuzziness)
    - `~N` after a phrase signifies slop amount
    - In order to search for any of these special characters, they will need to be escaped with \.
- "Specialized index" support
    - Specialized search index allows generating single-purpose elastic search indexes while re-using as much XF search infrastructure as possible
      Elasticsearch index is more akin to an SQL table than an entire database, so for very specific tasks a single purpose search index works better.
      A separate index also allows changing indexing properties and re-indexing just that one index without impacting the main search index
    - Implementation of a specialized index notes;
      At the core, a XenForo search handler is used to drive the functionality.
        - The search handler should implement the interface `\SV\SearchImprovements\Search\Specialized\SpecializedData`
        - Use the types `MetadataStructure::KEYWORD` or `MetadataStructure::STR` fields in `setupMetadataStructure`.
          These types will be rewritten to add `.exact` & `.ngram` subfields. To skip this pass `['skip-rewrite' => true]` to the MetadataStructure::addField's 3rd argument.
            - `MetadataStructure::KEYWORD` - shortish text which is semi-structured such as tags or usernames
            - `MetadataStructure::STR` - Arbitrary text which uses phrases of text
        - Register the search handler with the following content type fields
            - `specialized_search_handler_class`
            - `entity`
        - This handler really shouldn't be registered with `search_handler_class` content type field.
        - Add the behavior `SV\SearchImprovements:SpecializedIndexable` to the entity.

## Specialized index implementation examples
```php
public static function getStructure(Structure $structure)
{
...
    $structure->behaviors['SV\SearchImprovements:SpecializedIndexable'] = [
        'content_type' => 'myContentType',
        'checkForUpdates' => ['myField','description'],
    ];
...
}
...
public function setupMetadataStructure(MetadataStructure $structure)
{
    // this field will be rewritten for querying with getQueryForSpecializedSearch
    $structure->addField('myField', MetadataStructure::KEYWORD);
    // skip rewriting
    $structure->addField('description', MetadataStructure::STR, ['skip-rewrite' => true]);
}
```

## Specialized index usage example
```php
/** @var SpecializedSearchIndex $repo */
$repo = $this->repository('SV\SearchImprovements:SpecializedSearchIndex');
$query = $repo->getQueryForSpecializedSearch('myContentType');
$query->matchQuery($q, ['myField'])
      ->withNgram()
      ->withExact();
$myEntities = $repo->executeSearch($query, $maxResults)->getResultsData();
```
  
## Search result terms support

- Each search constraint needs a `svSearchConstraint.` prefixed phrase.
  Arrays are mapped to phrases by adding a `_` for each sub-array/key as such; `c[warning][points][lower]` => `svSearchConstraint.warning_points_lower`
- Each search order needs a `svSearchOrder.` prefixed phrase.
- Extend `XF\Entity\Search::getSpecializedSearchConstraintPhrase(string $key, $value): ?\XF\Phrase` to provide custom phrase handling (ie node names)
- Extend `XF\Entity\Search::formatConstraintValue(string $key, $value)` to provide custom formatting.
- Extend `XF\Entity\Search::setupConstraintFields(): void` to populate `$svDateConstraint`/`$svUserConstraint`/`$svIgnoreConstraint` properties which control formatting