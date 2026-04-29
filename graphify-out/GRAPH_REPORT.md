# Graph Report - .  (2026-04-29)

## Corpus Check
- Corpus is ~5,304 words - fits in a single context window. You may not need a graph.

## Summary
- 96 nodes · 111 edges · 9 communities detected
- Extraction: 74% EXTRACTED · 26% INFERRED · 0% AMBIGUOUS · INFERRED: 29 edges (avg confidence: 0.81)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Article Entity|Article Entity]]
- [[_COMMUNITY_Cascade Remove Mechanism|Cascade Remove Mechanism]]
- [[_COMMUNITY_Category Entity & Test|Category Entity & Test]]
- [[_COMMUNITY_Project Dependencies & Relations|Project Dependencies & Relations]]
- [[_COMMUNITY_Documentation Governance|Documentation Governance]]
- [[_COMMUNITY_Test Infrastructure|Test Infrastructure]]
- [[_COMMUNITY_Core Cascade Remove API|Core Cascade Remove API]]
- [[_COMMUNITY_Test Bootstrap|Test Bootstrap]]
- [[_COMMUNITY_Doctrine CLI Config|Doctrine CLI Config]]

## God Nodes (most connected - your core abstractions)
1. `Category` - 12 edges
2. `Article` - 9 edges
3. `oasis/doctrine-addon` - 9 edges
4. `Tag` - 7 edges
5. `CascadeRemoveTest` - 6 edges
6. `CascadeRemoveTrait` - 6 edges
7. `getId()` - 5 edges
8. `CascadeRemovableInterface` - 5 edges
9. `Test Entities (CMS Scenario)` - 5 edges
10. `TestEnv` - 4 edges

## Surprising Connections (you probably didn't know these)
- `Second Level Cache` --semantically_similar_to--> `Memcached`  [INFERRED] [semantically similar]
  README.md → PROJECT.md
- `AutoIdTrait Module` --semantically_similar_to--> `AutoIdTrait`  [INFERRED] [semantically similar]
  docs/state/architecture.md → README.md
- `CascadeRemove Module` --semantically_similar_to--> `CascadeRemoveTrait`  [INFERRED] [semantically similar]
  docs/state/architecture.md → README.md
- `CascadeRemove Module` --semantically_similar_to--> `CascadeRemovableInterface`  [INFERRED] [semantically similar]
  docs/state/architecture.md → README.md
- `Lifecycle Callback Approach` --semantically_similar_to--> `HasLifecycleCallbacks Annotation`  [INFERRED] [semantically similar]
  docs/manual/getting-started.md → README.md

## Hyperedges (group relationships)
- **Cascade Remove Mechanism** — readme_cascade_remove_trait, readme_cascade_removable_interface, readme_has_lifecycle_callbacks, readme_on_delete_cascade [EXTRACTED 1.00]
- **Entity Relation Types** — readme_strongly_related_entity, readme_loosely_related_entity, datamodel_get_cascade_removeable, datamodel_get_dirty_entities [EXTRACTED 1.00]
- **Documentation Governance System** — agents_ssot, agents_doc_layers, proposals_lifecycle, changes_version_changelog [INFERRED 0.80]

## Communities

### Community 0 - "Article Entity"
Cohesion: 0.11
Nodes (3): Article, getId(), Tag

### Community 1 - "Cascade Remove Mechanism"
Cohesion: 0.15
Nodes (14): onPostRemove Callback, onPreRemove Callback, Cache Invalidation Strategy, Lifecycle Callback Approach, CascadeRemovableInterface, Cascade Removal Problem, CascadeRemoveTrait, HasLifecycleCallbacks Annotation (+6 more)

### Community 2 - "Category Entity & Test"
Cohesion: 0.17
Nodes (1): Category

### Community 3 - "Project Dependencies & Relations"
Cohesion: 0.17
Nodes (13): Article-Category Relation, Article-Tag ManyToMany Relation, Category Self-Referential Relation, Test Entities (CMS Scenario), Composer, Doctrine ORM, Memcached, MySQL (+5 more)

### Community 4 - "Documentation Governance"
Cohesion: 0.15
Nodes (13): Documentation Layering System, Spec Workflow, SSOT (Single Source of Truth), Unreleased Changes Directory, Version CHANGELOG, $id Field (AutoIdTrait), L-Series Issues (Production Bugs), Release Issues (Stabilize Phase) (+5 more)

### Community 5 - "Test Infrastructure"
Cohesion: 0.29
Nodes (2): CascadeRemoveTest, TestEnv

### Community 6 - "Core Cascade Remove API"
Cohesion: 0.32
Nodes (4): getCascadeRemoveableEntities(), getDirtyEntitiesOnInvalidation(), findCascadeDetachableEntities(), onPreRemove()

### Community 7 - "Test Bootstrap"
Cohesion: 1.0
Nodes (0): 

### Community 8 - "Doctrine CLI Config"
Cohesion: 1.0
Nodes (0): 

## Knowledge Gaps
- **14 isolated node(s):** `PHPUnit`, `Composer`, `oasis/logging`, `Doctrine DBAL`, `@internal Annotation Convention` (+9 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **Thin community `Test Bootstrap`** (1 nodes): `bootstrap.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Doctrine CLI Config`** (1 nodes): `cli-config.php`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `oasis/doctrine-addon` connect `Project Dependencies & Relations` to `Cascade Remove Mechanism`, `Documentation Governance`?**
  _High betweenness centrality (0.132) - this node is a cross-community bridge._
- **Why does `CascadeRemoveTrait` connect `Cascade Remove Mechanism` to `Project Dependencies & Relations`?**
  _High betweenness centrality (0.091) - this node is a cross-community bridge._
- **Why does `AutoIdTrait` connect `Documentation Governance` to `Project Dependencies & Relations`?**
  _High betweenness centrality (0.078) - this node is a cross-community bridge._
- **What connects `PHPUnit`, `Composer`, `oasis/logging` to the rest of the system?**
  _14 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Article Entity` be split into smaller, more focused modules?**
  _Cohesion score 0.11 - nodes in this community are weakly interconnected._