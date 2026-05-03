# Summary

<!-- What changes and why, in 1-2 sentences. -->

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor
- [ ] Build / CI / tooling
- [ ] Documentation
- [ ] Dependency update

## Affected plugin(s)

- [ ] internal-comments
- [ ] linked-orders-for-woocommerce
- [ ] locked-payment-methods-for-woocommerce
- [ ] Monorepo scaffold (root scripts, wp-env, CI workflows)

## Checklist

- [ ] Changelog fragment added (`composer changelog:add` at root, or in the affected plugin dir)
- [ ] Tests added/updated
- [ ] `composer plugins:quality-check` (or per-plugin `composer quality-check`) passes locally
- [ ] Plugin header + `readme.txt` updated if runtime requirements changed

## Breaking changes for mirror consumers

<!-- Fill in if this PR changes the scoping pipeline, composer scripts, CI workflow contracts, or anything that downstream consumers of the split-published mirrors (wp-internal-comments / wc-linked-orders / wc-locked-payment-methods) would notice. Otherwise delete. -->

