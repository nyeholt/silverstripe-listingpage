# Changelog

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](http://semver.org/).

## [2.0.0]

* Upgrade from Silverstripe 3.x to 4.x
* Remove SortableChildrenExtension as it hasn't been used since Silverstripe 2.X

## 2013-08-26 v1.0.1

* FIX Security patch to fix potential SQL injection point in LIMIT clause 
  (though the framework enforces is_numeric anyway)

## 2013-01-13 v1.0.0

* SS3 first stable update. Note that pagination now does NOT filter canView 
  items; you must do this yourself from the template

## 2011-09-26 v0.2.6

* Make sure the type for the listing source is correctly mapped

## 2011-09-16 v0.2.5

* Removed restrictions that meant a ParentID was expected for a content type,
  making it possible to list arbitrary data types

## 2011-08-01 v0.2.4

* Added ability to output different content type headers to allow XML feeds
  to be generated via the listing page

## 2011-07-14 v0.2.3

* Added an extension that allows for filtering children of nodes from templates

## 2011-04-28 v0.2.2

* Slightly smarter source node selection

## 2011-03-10 v0.2.0

* Split the template definition off into a separate section for security and
  reusability reasons. 
