# Search Improvements

A collection of improvements to XF's enhanced search and XenForo's default MySQL search.

- Allow * (or empty search string) to return results, for MySQL and XFES
- range_query search DSL
 - allows arbitrary range queries for numerical data
- Allow users to select the default search order independent for the forum wide setting.

Elastic Search only features:
- Restore default search order option
- Per content type weighting
- Adds Elastic Search information to the AdminCP home screen.
- Adds a debug option to log the search DSL queries to error log for troubleshooting
- Option to extend search syntax to permit;
 - + signifies AND operation
 - | signifies OR operation
 - - negates a single token
 - " wraps a number of tokens to signify a phrase for searching
 - * at the end of a term signifies a prefix query
 - ( and ) signify precedence
 - ~N after a word signifies edit distance (fuzziness)
 - ~N after a phrase signifies slop amount
 - In order to search for any of these special characters, they will need to be escaped with \.