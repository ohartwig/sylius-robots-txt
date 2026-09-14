<!--
SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
SPDX-License-Identifier: MIT
-->

# koh/sylius-robots-txt

Channel-aware `robots.txt` for Sylius, with explicit AI crawler rules and
[Content Signals](https://contentsignals.org/).

Three things that usually live in a hand-edited static file, modelled instead:
one `robots.txt` per channel, a named group per AI crawler, and a Content Signal
that is emitted both in the file and as a response header.

## Install

```bash
composer require koh/sylius-robots-txt
```

```php
// config/bundles.php
Koh\SyliusRobotsTxt\KohSyliusRobotsTxtBundle::class => ['all' => true],
```

```yaml
# config/routes/koh_agent.yaml
koh_robots_txt:
    resource: '@KohSyliusRobotsTxtBundle/config/routes.php'
    ignore_errors: not_found
```

**Delete `public/robots.txt`.** The web server answers from disk before Symfony
is consulted, so a leftover file makes this bundle silently do nothing. The
bundle logs a warning at cache warmup if it finds one.

## Configure

Defaults are sensible for a shop that wants to be found and answered from, but
not trained on:

```yaml
# config/packages/koh_robots_txt.yaml
koh_robots_txt:
    disallow: ['/admin', '/_mcp', '/mcp/oauth/']
    sitemaps: ['/sitemap.xml']          # relative paths get expanded per channel

    ai_crawlers:
        allowed: true                   # false ⇒ Disallow: / for every named token
        agents: [GPTBot, OAI-SearchBot, ClaudeBot, Google-Extended, …]
        disallow: []                    # extra paths hidden from AI crawlers only

    content_signal:
        search: true                    # may be indexed and linked
        ai_input: true                  # may be retrieved at answer time (RAG)
        ai_train: false                 # may not be used to train a model
        response_header: true           # also emit Content-Signal on HTML pages
```

## Output

```text
User-agent: *
Content-Signal: ai-train=no, search=yes, ai-input=yes
Allow: /
Disallow: /admin
Disallow: /_mcp

# AI crawlers, named explicitly.
# A wildcard group is not read as a policy for these agents.
User-agent: GPTBot
User-agent: OAI-SearchBot
…
Content-Signal: ai-train=no, search=yes, ai-input=yes
Allow: /

Sitemap: https://shop.example.com/sitemap.xml
```

Why the AI crawlers get their own group rather than being folded into `*`:
several of them only honour a policy that names their product token, and
scanners check for the tokens specifically. `User-agent: *` is not read as a
statement about GPTBot.

Why the Content Signal also goes out as a header: `robots.txt` states a policy
for a crawl, the header states it for the exact resource that was fetched —
which is what a retrieval-time agent sees. Different consumers, same
configuration.

## Command

```bash
bin/console koh:agent:robots:dump                  # print for the first enabled channel
bin/console koh:agent:robots:dump -c WEB_AT        # print for one channel
bin/console koh:agent:robots:dump --write          # write public/robots.txt
```

`--write` is for deployments that prefer a static file. It makes the dynamic
route unreachable, so re-run it whenever the configuration changes.

## Decommission

Remove the `composer require`, the `config/bundles.php` entry, the route import
and `config/packages/koh_robots_txt.yaml`. No database table, no cache pool.
Restore a static `public/robots.txt` if you still want one.

## Security

Report vulnerabilities privately — see [SECURITY.md](SECURITY.md). Advisories
are published as CSAF 2.0 at <https://ole-hartwig.eu/.well-known/csaf/>.

## Licence

MIT — see [LICENSE](LICENSE).
