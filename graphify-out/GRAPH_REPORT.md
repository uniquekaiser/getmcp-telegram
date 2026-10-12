# Graph Report - getmcp-telegram  (2026-10-11)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 166 nodes · 241 edges · 13 communities (5 shown, 8 thin omitted)
- Extraction: 70% EXTRACTED · 30% INFERRED · 0% AMBIGUOUS · INFERRED: 72 edges (avg confidence: 0.86)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Community 0
- Community 1
- Community 3
- Community 4
- Community 5
- Community 6
- Community 7
- Community 8

## God Nodes (most connected - your core abstractions)
1. `Fault` - 29 edges
2. `Store` - 25 edges
3. `Plugin` - 16 edges
4. `Catalog` - 15 edges
5. `Vault` - 11 edges
6. `Client` - 10 edges
7. `Integration` - 10 edges
8. `Normalize` - 10 edges
9. `Diagnostics` - 9 edges
10. `Developer handoff` - 9 edges

## Surprising Connections (you probably didn't know these)
- `Developer handoff` --references--> `Catalog`  [INFERRED]
  docs/DEVELOPMENT.md → includes/Catalog.php
- `Developer handoff` --references--> `Client`  [INFERRED]
  docs/DEVELOPMENT.md → includes/Client.php
- `Developer handoff` --references--> `Diagnostics`  [INFERRED]
  docs/DEVELOPMENT.md → includes/Diagnostics.php
- `Developer handoff` --references--> `Integration`  [INFERRED]
  docs/DEVELOPMENT.md → includes/Integration.php
- `Developer handoff` --references--> `Normalize`  [INFERRED]
  docs/DEVELOPMENT.md → includes/Normalize.php

## Import Cycles
- None detected.

## Communities (13 total, 8 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.10
Nodes (5): Diagnostics, Fault, Integration, {closure#13}(), Vault

### Community 1 - "Community 1"
Cohesion: 0.10
Nodes (6): Normalize, {closure#16}(), {closure#18}(), {closure#22}(), Plugin, Store

### Community 3 - "Community 3"
Cohesion: 0.12
Nodes (5): Catalog, {closure#5}(), {closure#7}(), {closure#3}(), req()

### Community 4 - "Community 4"
Cohesion: 0.15
Nodes (9): Developer handoff, Migration and rollback, Verification status — development preview 0.1.0, Coverage, Development, GetMCP Telegram Extension, Hosting, Installation and connection (+1 more)

### Community 6 - "Community 6"
Cohesion: 0.18
Nodes (10): config, allow-plugins, sort-packages, description, license, name, require, danog/madelineproto (+2 more)

## Knowledge Gaps
- **15 isolated node(s):** `name`, `description`, `type`, `license`, `php` (+10 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 88 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **8 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Fault` connect `Community 0` to `Community 1`, `Community 3`, `Community 5`, `Community 7`, `Community 8`?**
  _High betweenness centrality (0.242) - this node is a cross-community bridge._
- **Why does `Developer handoff` connect `Community 4` to `Community 0`, `Community 1`, `Community 3`, `Community 5`?**
  _High betweenness centrality (0.157) - this node is a cross-community bridge._
- **Why does `Plugin` connect `Community 1` to `Community 0`, `Community 2`, `Community 3`, `Community 4`?**
  _High betweenness centrality (0.149) - this node is a cross-community bridge._
- **Are the 25 inferred relationships involving `Fault` (e.g. with `.arguments()` and `.validate()`) actually correct?**
  _`Fault` has 25 INFERRED edges - model-reasoned connections that need verification._
- **Are the 15 inferred relationships involving `Store` (e.g. with `Developer handoff` and `.publish()`) actually correct?**
  _`Store` has 15 INFERRED edges - model-reasoned connections that need verification._
- **Are the 2 inferred relationships involving `Plugin` (e.g. with `Developer handoff` and `.publish()`) actually correct?**
  _`Plugin` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Are the 9 inferred relationships involving `Catalog` (e.g. with `Developer handoff` and `.invoke()`) actually correct?**
  _`Catalog` has 9 INFERRED edges - model-reasoned connections that need verification._