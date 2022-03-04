# Search Improvements

A collection of improvements to XF's enhanced search and XenForo's default MySQL search.

- Allow `*` (or empty search string) to return results, for MySQL and XFES
- range_query search DSL
- allows arbitrary range queries for numerical data
- Allow users to select the default search order independent for the forum wide setting.

Elasticsearch only features:
- Restore default search order option
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
      Elasticsearch index is more akin to an SQL table than an entire database, so for very specific tasks a single purpose search index works better
    - Implementation bits;
        - A XenForo search handler must implement; `\SV\SearchImprovements\Search\Specialized\SpecializedData`
            - This handler really shouldn't be registered with `search_handler_class` content type field.
        - The following content type fields must be implemented;
          - specialized_search_handler_class
          - entity
        - Add the behavior `SV\SearchImprovements:SpecializedIndexable` to the entity.
```php
$structure->behaviors['SV\SearchImprovements:SpecializedIndexable'] = [
    'content_type' => 'sv_tag',
    'checkForUpdates' => ['tag'],
];
```
    - Usage example;
```php
/** @var SpecializedSearchIndex $repo */
$repo = $this->repository('SV\SearchImprovements:SpecializedSearchIndex');
$query = $repo->getQueryForSpecializedSearch('sv_tag');
$query->matchQuery($q, ['tag'])
      ->withNgram()
      ->withExact();
$hits = $repo->executeSearch($query, $maxResults);
$results = [];
foreach ($hits AS $tag)
{
    $tag = $tag['fields']['tag'] ?? null;
    if ($tag !== null)
    {
        $results[] = [
            'id' => $tag,
            'text' => $tag,
            'q' => $q
        ];
    }
}
  ```
  