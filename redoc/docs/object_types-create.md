## Object-types

This is where _Entities_ are defined for the objects to be stored. Entities are object that exists.
Entities don't have to do anything, they just exist. For an entity to be useful as an object, attributes are needed and can be specified in section of the dashboard.

In database administration, an entity can be a single thing, person, place, or object. Data can be stored about such entities. Linking to sources is not mandatory.

The object needs `name` and `function(role)` as a minimum to be stored.

You can configure access to objects in this tab.

`route` should be entered as : `/api/[PATH]`

```json
Properties
_name_:
_function_:
_endpoint_:
_route_:
_source_:
_description_:
```
