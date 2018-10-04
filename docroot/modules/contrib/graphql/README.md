[![Build Status](https://img.shields.io/travis/drupal-graphql/graphql.svg)](https://travis-ci.org/drupal-graphql/graphql)
[![Code Coverage](https://img.shields.io/codecov/c/github/drupal-graphql/graphql.svg)](https://codecov.io/gh/drupal-graphql/graphql)
[![Code Quality](https://img.shields.io/scrutinizer/g/drupal-graphql/graphql.svg)](https://scrutinizer-ci.com/g/drupal-graphql/graphql/?branch=8.x-3.x)

# GraphQL for Drupal

This module lets you craft and expose a [GraphQL](http://graphql.org/) schema for [Drupal 8](https://www.drupal.org/8).

It is is built around [https://github.com/webonyx/graphql-php](https://github.com/webonyx/graphql-php). As such, it supports
the full official GraphQL specification with all its features.

You can use this module as a foundation for building your own schema through
custom code or you can use and extend the generated schema using the plugin
architecture and the provided plugin implementations form the sub-module.

For ease of development, it includes the [GraphiQL](https://github.com/graphql/graphiql/) interface at`/graphql/explorer`.

## Quickstart

To get a quick overview from the __query__ part of GraphQL in action watch the following video's.

[![Headless Drupal with GraphQL from scratch - part 01](https://img.youtube.com/vi/Fx1Gz-BVNx8/0.jpg)](https://www.youtube.com/watch?v=Fx1Gz-BVNx8)
[![Headless Drupal with GraphQL from scratch - part 02](https://img.youtube.com/vi/Q0hTG5ASzx0/0.jpg)](https://www.youtube.com/watch?v=Q0hTG5ASzx0)

## Example implementation

Check out [https://github.com/fubhy/drupal-decoupled-app](https://github.com/fubhy/drupal-decoupled-app) for a complete example
of a fully decoupled React and GraphQL application. Feel free to use that
repository as a starting point for your own decoupled application.

## Documentation

[Documentation](doc/SUMMARY.md) is heavily worked on, but still far from complete.
This is due to the vast amount of improvements and additional features we've
added to the module recently. As we are finishing up the 3.x version of this
module we will be re-doing the documentation and record a series of screencasts.

These blog posts provide additional information on how to use and extend the module
as well as other other contributed modules supporting it:

* [https://www.amazeelabs.com/en/blog/graphql-introduction](https://www.amazeelabs.com/en/blog/graphql-introduction)
* [https://www.amazeelabs.com/en/blog/drupal-graphql-react-apollo](https://www.amazeelabs.com/en/blog/drupal-graphql-react-apollo)
* [https://www.amazeelabs.com/en/blog/drupal-graphql-batteries-included](https://www.amazeelabs.com/en/blog/drupal-graphql-batteries-included)
* [https://www.amazeelabs.com/en/blog/extending-graphql-part1-fields](https://www.amazeelabs.com/en/blog/extending-graphql-part1-fields)
* [https://www.amazeelabs.com/en/blog/extending-graphql-part-2](https://www.amazeelabs.com/en/blog/extending-graphql-part-2)
* [https://www.amazeelabs.com/en/blog/graphql-for-drupalers-fields](https://www.amazeelabs.com/en/blog/graphql-for-drupalers-fields)
* [https://www.amazeelabs.com/en/blog/extending-graphql-part-3-mutations](https://www.amazeelabs.com/en/blog/extending-graphql-part-3-mutations)
* [https://www.amazeelabs.com/en/blog/dont-push-it-using-graphql-twig](https://www.amazeelabs.com/en/blog/dont-push-it-using-graphql-twig)

## Resources

* Project homepage: [https://www.drupal.org/project/graphql](https://www.drupal.org/project/graphql)
* Contributing: [https://github.com/drupal-graphql/graphql](https://github.com/drupal-graphql/graphql)

## Related projects

* GraphQL APQ [https://github.com/lucasconstantino/drupal-graphql-apq](https://github.com/lucasconstantino/drupal-graphql-apq)
  > Drupal module for Automatic Persisted Queries compatible with the apollo-link-persisted-queries project's proposed protocol.
* GraphQL Metatag [https://github.com/drupal-graphql/graphql-metatag](https://github.com/drupal-graphql/graphql-metatag)
  > Module that integrates the Metatag Drupal module with GraphQL.
* GraphQL Twig [https://github.com/drupal-graphql/graphql-twig](https://github.com/drupal-graphql/graphql-twig)
  > Allows you to inject data into Twig templates by simply adding a GraphQL query.
* GraphQL Views [https://github.com/drupal-graphql/graphql-views](https://github.com/drupal-graphql/graphql-views)
  > Adds support for views to GraphQL.
