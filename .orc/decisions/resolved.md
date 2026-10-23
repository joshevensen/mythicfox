# Resolved decisions

Rules `plan` and `build` apply instead of asking.

## Covering Inertia page template changes when the page has no vitest test
- applies when: a Vue page/component under resources/js changes and has no vitest component test
- choose: Pest Inertia prop assertions plus `file_get_contents` source assertions on the `.vue` file, with `types:check`
- why: avoids building a component-test harness for one page; server behavior is covered by prop tests
- from: #66, #70, #71
