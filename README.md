# Pollaris, modified — polls.digitalsme.eu

The modified source of [Pollaris](https://framagit.org/pollaris/pollaris) as
it runs at **https://polls.digitalsme.eu**.

This repository exists to satisfy section 13 of the AGPL-3.0, which requires
that users interacting with the program over a network can obtain the source
of the version actually running, not merely the original one.

- **Base version:** Pollaris 1.2.3
- **Licence:** AGPL-3.0-or-later, like the original
- **Original author:** Marien Fressinaud

## What was changed

| File | Change |
|---|---|
| `custom/templates/emails/new_poll_admin.txt.twig` | adds the public poll link, labels both links explicitly, adds the instance signature |
| `custom/templates/emails/new_vote.txt.twig` | adds the instance signature |
| `custom/templates/emails/new_comment.txt.twig` | adds the instance signature |
| `custom/templates/emails/polls_list.txt.twig` | adds the instance signature |
| `custom/templates/home/show.html.twig` | title and introduction from our own translation domain; create button only for authenticated users; source link points here |
| `custom/translations/digitalsme+intl-icu.en_GB.yaml` | our own strings, in a separate domain so the upstream translations stay intact |
| `custom/config/packages/security.yaml` | creating and managing polls requires an account; voting stays public |
| `custom/custom.css` | DIGITAL SME colours and logo |

Two notes on the changes.

The e-mail labels reuse translation keys that already exist upstream, so no
untranslated string is introduced.

The access rules are anchored deliberately. `^/polls/new` without an anchor
would also match `/polls/newsletter-2026` and block voting on any poll whose
slug begins with "new", because `access_control` matches regular expressions
against the path rather than against routes.

## Building

`Dockerfile` downloads Pollaris 1.2.3 from the official source and applies the
files under `custom/` on top of it. They are copied into the image before the
templates are compiled, because Twig does not reload templates in production.

```bash
docker build --target runtime -t pollaris-app .
docker build --target web -t pollaris-web .
```

Runtime configuration, environment variables and infrastructure are not part
of this repository: they are not part of the program's source.

## Contributing back

The change to the creation e-mail, the public link and the labels, is useful
to anyone running Pollaris and is proposed to the original project. If it is
accepted, the corresponding local override disappears.
