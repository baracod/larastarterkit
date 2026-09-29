# Third-party notices

Baracod source is distributed under the MIT license declared by this project.
Third-party dependencies retain their own licenses. Composer and pnpm resolve
those dependencies independently; vendor and node_modules are excluded from archives.

The shared interface derives from [Sneat / ThemeSelection](https://themeselection.com/).
On 2026-09-29 the maintainer confirmed authorization to redistribute it publicly
as a starter. This records that declaration; no specific edition or license
certificate has been supplied in this repository. Original notices remain applicable.

The generated Iconify CSS contains selections or conversions from these icon sets:

| Set | Author | Declared license |
| --- | --- | --- |
| [BoxIcons](https://github.com/atisawd/boxicons) | Atisa | [CC-BY-4.0](https://creativecommons.org/licenses/by/4.0/) |
| [Material Design Icons](https://github.com/Templarian/MaterialDesign) | Pictogrammers | [Apache-2.0](https://github.com/Templarian/MaterialDesign/blob/master/LICENSE) |
| [Tabler Icons](https://github.com/tabler/tabler-icons) | Paweł Kuna | [MIT](https://github.com/tabler/tabler-icons/blob/master/LICENSE) |
| [Font Awesome 4](https://github.com/FortAwesome/Font-Awesome/tree/fa-4) | Dave Gandy | [OFL-1.1](https://scripts.sil.org/cms/scripts/page.php?site_id=nrsi&id=OFL) |
| [Fluent UI System Icons](https://github.com/microsoft/fluentui-system-icons) | Microsoft Corporation | [MIT](https://github.com/microsoft/fluentui-system-icons/blob/main/LICENSE) |

Release preparation commands:

- `composer licenses --format=json`
- `pnpm licenses list --json`
- Review bundled images, fonts, styles and template components separately.
