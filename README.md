# PluginForge WordPress SEO Checker

Free SEO analysis tool for websites and WordPress sites by [PluginForge | WordPress](https://pluginforgewordpress.com/).

Analyze any public URL and generate a detailed, shareable SEO report covering on-page SEO, technical signals, structured data, social metadata, links, images, crawling directives and indexability.

## 🚀 Try the Free SEO Checker

Analyze a website for free:

| Language | Link |
|---|---|
| 🇧🇷 Português (Brasil) | https://pluginforgewordpress.com/pt-br/tools/wordpress-seo-checker |
| 🇺🇸 English | https://pluginforgewordpress.com/en/tools/wordpress-seo-checker |
| 🇪🇸 Español | https://pluginforgewordpress.com/es/tools/wordpress-seo-checker |
| 🇨🇳 简体中文 | https://pluginforgewordpress.com/zh-cn/tools/wordpress-seo-checker |

No installation is required. Enter a public URL and the tool generates a report with an SEO score and detailed findings.

## 🔎 What the Tool Checks

The PluginForge WordPress SEO Checker analyzes a wide range of SEO and technical signals, including:

### On-page SEO
- Page title and title length
- Meta description and description length
- H1 and H2 structure
- Heading hierarchy
- Canonical URL
- HTML language attribute
- Mobile viewport

### Search engine directives
- HTTPS
- Meta robots
- X-Robots-Tag
- robots.txt
- Sitemap XML
- General indexability
- Crawl and indexing-related signals

### International SEO
- hreflang detection
- Localized page signals
- Language and locale information

### Social metadata
- Open Graph
- Twitter/X Cards
- Social titles and descriptions
- Social image detection

### Structured data
- JSON-LD
- Schema.org
- Detected structured-data types
- Structured-data properties and relationships

### Images
- Total image count
- Images missing ALT attributes
- Image URL samples
- Basic image accessibility signals

### Links
- Internal links
- External links
- Nofollow attributes
- Link samples and destinations

## 📊 SEO Score

Each completed analysis receives an SEO score together with categorized findings.

The report separates issues into different levels so you can quickly identify what needs attention and inspect the underlying technical data when you need more detail.

## 📄 Shareable SEO Reports

Every completed audit can generate a public report URL.

This makes the tool useful for:

- Website owners
- WordPress developers
- Freelancers
- SEO professionals
- Digital agencies
- Small businesses
- WooCommerce site owners
- Developers auditing client websites

A report can be shared with a client, developer, marketing team or anyone responsible for improving a website.

## 🌍 Multilingual

The tool is available in four languages:

- Portuguese (Brazil)
- English
- Spanish
- Simplified Chinese

The same analysis engine is used across localized versions of the tool.

## 🔐 Security

Because the analyzer fetches URLs supplied by users, URL validation is an important part of the project.

The implementation includes protection against requests to local and reserved network destinations, including:

- Loopback addresses
- Private IPv4 networks
- Private IPv6 destinations
- Reserved network addresses
- Localhost targets
- Cloud metadata-style addresses

The application also applies per-IP request limiting to help protect the public analysis endpoint.

## ⚡ Public Web Tool

The live version is hosted by PluginForge:

**PluginForge | WordPress**
https://pluginforgewordpress.com/

### Useful PluginForge resources
- WordPress SEO Checker: https://pluginforgewordpress.com/en/tools/wordpress-seo-checker
- WordPress Plugins: https://pluginforgewordpress.com/en/category-plugins
- Templates: https://pluginforgewordpress.com/en/category-templates
- Addons: https://pluginforgewordpress.com/en/category-addons
- Blog: https://pluginforgewordpress.com/en/catalog-blog

### For Portuguese visitors
- PluginForge | WordPress: https://pluginforgewordpress.com/pt-br
- Plugins: https://pluginforgewordpress.com/pt-br/category-plugins
- Templates: https://pluginforgewordpress.com/pt-br/category-templates
- Addons: https://pluginforgewordpress.com/pt-br/category-addons

## 🧩 Project Structure

This repository contains the PluginForgeTools module used by the PluginForge web application.

The SEO Checker includes components for:

```
PluginForgeTools/
├── Boot.php
├── config.json
├── Controllers/
│   └── Front/
├── Database/
│   └── Migrations/
├── Jobs/
├── Lang/
│   ├── en/
│   ├── es/
│   ├── pt-br/
│   └── zh-cn/
├── Models/
├── Requests/
├── Routes/
└── Services/
    ├── PageFetcher.php
    ├── SeoAnalyzer.php
    ├── SeoScoreCalculator.php
    └── UrlSecurityValidator.php
```

## 🛠️ Technology

The module is designed to run within an InnoShop/Laravel-based application and uses the application's routing, queue, database, localization and view systems.

The project uses asynchronous analysis jobs so that larger audits can be processed without keeping the visitor waiting on a long-running HTTP request.

## 🧪 Validation

The analyzer has been tested against different types of publicly accessible websites, including:

- Standard HTML websites
- example.com
- Real WordPress websites
- Sites using JSON-LD and Schema.org
- Sites using Open Graph and Twitter/X Cards
- Websites with XML sitemaps and robots.txt files

The purpose of these tests is to verify that the analyzer responds to the actual HTML and HTTP signals returned by each website rather than relying on fixed assumptions.

## 💡 Example Use Cases

**Website owners**
Run a quick technical SEO audit before publishing a new page.

**WordPress users**
Check whether important SEO, social, structured-data and indexing signals are present on a WordPress page.

**SEO professionals**
Use the report as a fast first-pass technical audit before deeper analysis.

**Freelancers and agencies**
Generate a shareable report that can be sent directly to a client.

**Developers**
Inspect the underlying SEO signals returned by a public website.

## 🤝 Contributions

Issues, bug reports and technical suggestions are welcome.

When reporting a problem, please include:

- The URL that was analyzed, when it is publicly shareable.
- The expected behavior.
- The observed behavior.
- The relevant section of the generated report.
- Any reproducible steps.

## 🔗 Links

- Live tool: https://pluginforgewordpress.com/en/tools/wordpress-seo-checker
- PluginForge | WordPress: https://pluginforgewordpress.com/
- GitHub: https://github.com/PluginForge-Wordpress/wordpress-seo-checker

## 📌 About PluginForge

PluginForge | WordPress develops and distributes WordPress-focused tools and products covering security, SEO, performance, automation, WooCommerce, website management and developer workflows.

Learn more: https://pluginforgewordpress.com/

---

*PluginForge WordPress SEO Checker — free website and WordPress SEO analysis with shareable public reports.*
