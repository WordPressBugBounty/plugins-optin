# WowShipping Admin-to-Admin Statistics Email Recommendations

## Objective

Design a weekly or monthly self-email sent from the WowShipping plugin to the site admin that:

1. shows business impact clearly,
2. reminds free users that the plugin is producing measurable value,
3. exposes missed revenue and optimization opportunities,
4. makes upgrading to Pro feel like the logical next step.

This document focuses on which analytics should be shown in that email, why they matter for conversion to Pro, and whether the data is already available in the current WowOptin-style analytics stack or would need additional instrumentation.

## Executive Recommendation

If the email is meant to convert free users to Pro, the best analytics are not just vanity metrics. The strongest metrics are the ones that answer these four questions:

1. How much value did the plugin create?
2. Where is the user losing money or leads right now?
3. Which audience segment is being underserved?
4. What would Pro unlock that would improve these numbers?

The strongest primary metrics to include are:

1. **Attributed leads captured**
2. **Attributed conversions and conversion rate**
3. **Attributed revenue or purchase influence**
4. **Top-performing vs underperforming campaign/widget**
5. **Mobile vs desktop performance gap**
6. **Audience/location performance split**
7. **Trend change vs previous period**
8. **Missed opportunity estimate**

If only four metrics are shown above the fold, these are the best four:

1. **Leads captured this period**
2. **Conversion rate this period and change vs previous period**
3. **Revenue influenced this period**
4. **Missed leads or revenue estimate from underperforming traffic**

Those four together create the clearest upgrade narrative: the plugin is already generating value, there is measurable upside left on the table, and Pro is the tool that helps recover that upside.

## What the Current Analytics Stack Already Supports

Based on the current codebase, the existing analytics model already tracks enough data to support a meaningful first version of this email.

### Already trackable now

1. **Views / impressions**
2. **Unique views by IP**
3. **Conversions** from click goals and purchases
4. **Conversion rate**
5. **Revenue influenced** through WooCommerce or EDD purchase attribution
6. **Device split** for impressions
7. **Country / geo distribution**
8. **Popular optins/widgets**
9. **Leads captured**
10. **Lead source integration type** such as Mailchimp, HubSpot, FluentCRM, etc.
11. **Social click counts** per platform for some optins
12. **Weekly visitor and pageview trend deltas**

### Present in the data model, but not fully exposed in a polished reporting layer

1. **Conversion path / page path** on click events
2. **User ID when available**
3. **Timestamp-level interaction data** that can support day-of-week and hour-of-day reports
4. **Purchase linkage** to specific order IDs
5. **Lead records linked to specific conversion IDs**

### Likely requires new instrumentation or new aggregation logic

1. **Per-page impression performance**
2. **Traffic-source performance by interaction**
3. **Funnel step drop-off** for form start -> submit -> lead sync
4. **CTA copy or button-level performance**
5. **Offer-level analytics** such as coupon revealed, copied, redeemed
6. **Estimated lost revenue / recovered revenue model**
7. **Segment-based lift projections**

## Best Analytics to Show in the Email

## 1. Attributed Leads Captured

### Why this is one of the best conversion metrics

For non-commerce sites, leads are the clearest value signal. Free users understand a number like "You captured 38 leads this week" immediately. It is simple, outcome-based, and tied to business growth rather than plugin activity.

This is stronger than raw impressions because it answers: "What did the plugin produce?"

### What to show

1. Total leads captured this week or month
2. Percent change from previous comparable period
3. Top lead-generating campaign/widget
4. Best-connected integration destination

### Suggested email copy angle

"Your on-site campaigns captured 38 new leads this week. Your best campaign generated 21 of them. Pro can help you capture more by targeting high-intent visitors more precisely."

### Conversion value

This metric converts well because it frames the plugin as a growth tool, not a design widget.

### Feasibility

**Available now.** Lead rows already exist and are linked to a conversion ID and an integration type.

## 2. Attributed Conversions and Conversion Rate

### Why it matters

A free user may not know whether their campaigns are efficient. Conversion rate tells them whether traffic is being turned into action. It is one of the most persuasive optimization metrics because it ties exposure to outcome.

### What to show

1. Total conversions this period
2. Overall conversion rate
3. Change vs previous period
4. Best-performing campaign by conversion rate
5. Worst-performing campaign with enough traffic to matter

### Suggested email copy angle

"Your campaigns converted 4.8% of viewers this week, up 18% from last week. One campaign reached 9.2%, while another high-traffic campaign stayed at 1.1%, leaving room for improvement."

### Why this drives upgrades

When users see a clear gap between high and low performers, they become more open to Pro features that promise testing, segmentation, scheduling, targeting, and advanced optimization.

### Feasibility

**Available now.** Views, clicks, and purchases are already stored and quick-view conversion rate is already computed.

## 3. Revenue Influenced

### Why it matters

For WooCommerce or EDD stores, revenue is the highest-intent metric in the email. A free user can ignore impressions. They do not ignore money.

If the plugin can say, "Your campaigns influenced $426 this month," the upgrade discussion becomes much easier.

### What to show

1. Total influenced revenue this period
2. Change vs previous period
3. Top revenue-driving campaign/widget
4. Number of attributed purchases
5. Average revenue per converting campaign or per purchase

### Suggested email copy angle

"Your campaigns influenced $426.00 in sales this month across 11 attributed purchases. Your top campaign drove 54% of that revenue."

### Why this drives upgrades

Revenue-based reporting creates an ROI narrative. Once users see direct financial impact, Pro feels like an investment, not a cost.

### Feasibility

**Available now for stores where purchase attribution works.** Purchase linkage already exists for WooCommerce and EDD order completion.

## 4. Top Performer vs Underperformer

### Why it matters

Admins do not only want totals. They want to know what is working and what is wasting opportunity.

Showing the best performer alone feels like celebration. Showing best and worst together creates urgency.

### What to show

1. Highest-performing campaign by conversion rate with a minimum traffic threshold
2. Highest-traffic campaign
3. Lowest-performing high-traffic campaign
4. Lead or revenue contribution of the top campaign
5. Missed upside on the low performer

### Suggested email copy angle

"Your best campaign converted at 8.4%. Your most-viewed campaign converted at only 1.3%. Matching your top campaign's performance would have produced an estimated 27 extra leads this month."

### Why this drives upgrades

This metric naturally creates a Pro CTA around optimization, A/B testing, better targeting, scheduling, advanced templates, or audience segmentation.

### Feasibility

**Mostly available now.** Per-campaign views, conversions, leads, and revenue can be derived. The missed-opportunity estimate requires a lightweight calculation layer.

## 5. Mobile vs Desktop Performance Gap

### Why it matters

Many free users suspect mobile performance issues but do not have clear proof. If the email shows that mobile drives most views but converts far worse than desktop, that becomes a concrete reason to upgrade for device-specific design and targeting controls.

### What to show

1. Share of views by device
2. Share of conversions by device
3. Conversion rate by device
4. Gap between mobile and desktop conversion rate
5. Suggested action tied to Pro feature positioning

### Suggested email copy angle

"Mobile delivered 72% of your campaign views but converted 41% worse than desktop. This is your biggest optimization gap right now."

### Why this drives upgrades

Device-specific underperformance is extremely persuasive because it implies easy recoverable upside.

### Feasibility

**Partially available now.** Device is already stored on interactions, and impression-by-device exists. Full device-specific conversion reporting needs an additional aggregation query.

## 6. Geographic / Audience Distribution

### Why it matters

When users see that a large share of their campaign traffic comes from specific countries or audience segments, it creates a natural argument for localized messaging, scheduling by timezone, and targeted offers.

### What to show

1. Top countries by traffic
2. Top countries by conversion rate
3. Country mismatch cases where a high-traffic country converts poorly
4. Unknown-country share
5. Opportunity note such as timezone-based scheduling or localized offer copy

### Suggested email copy angle

"Your top traffic countries were the United States, the United Kingdom, and Canada. Canada converted 2.1x better than your global average, suggesting a strong audience segment worth targeting more precisely."

### Why this drives upgrades

Audience-specific performance makes advanced targeting feel useful and concrete.

### Feasibility

**Partially available now.** Country data exists. Country-based conversion aggregation would need a new reporting query.

## 7. Trend Change vs Previous Period

### Why it matters

Single numbers are weak without context. Relative trend is what makes a user feel progress or pain.

Every primary stat in the email should be shown with its previous-period delta.

### What to compare

1. Weekly vs previous week
2. Monthly vs previous month
3. For early-stage sites, a rolling 7-day vs prior 7-day comparison may be more stable than calendar month comparisons

### Best metrics to pair with trend deltas

1. Leads
2. Conversion rate
3. Revenue influenced
4. Views
5. Pageviews and visitors

### Why this drives upgrades

Trend creates emotional movement. A user is more likely to act when they see a decline to fix or a rise worth amplifying.

### Feasibility

**Available now for several metrics.** Some parts of the current quick-view logic are already weekly and previous-week based.

## 8. Missed Opportunity Estimate

### Why it matters

This is one of the strongest upgrade drivers if done carefully. Instead of only reporting what happened, the email estimates what the user likely left on the table.

Examples:

1. Extra leads possible if the highest-traffic campaign matched the conversion rate of the best campaign
2. Extra revenue possible if mobile conversion matched desktop conversion
3. Extra conversions possible if the lowest-performing top page matched site average

### Example formulas

1. **Missed leads** = high-traffic campaign views x (best CR - current CR)
2. **Missed revenue** = missed conversions x average order value
3. **Missed mobile conversions** = mobile views x (desktop CR - mobile CR)

### Suggested email copy angle

"Based on your current traffic, improving just one underperforming campaign to match your best performer could have generated an estimated 19 extra leads this month."

### Why this drives upgrades

This is where the upgrade narrative becomes strongest. It reframes Pro as upside recovery.

### Feasibility

**Derived metric.** Requires a calculation layer, not necessarily new event tracking.

## 9. Lead Capture Efficiency by Campaign

### Why it matters

Clicks and leads are not the same. Some campaigns get curiosity clicks but poor actual lead capture. Showing lead efficiency exposes form quality and offer quality.

### What to show

1. Views
2. Click conversions
3. Leads captured
4. Lead rate from views
5. Lead rate from click-to-submit

### Why this drives upgrades

This supports Pro positioning around better templates, multi-step flows, advanced forms, and testing.

### Feasibility

**Partially available now.** Views and leads exist; click-to-lead funnel stitching may need clearer event linkage.

## 10. Integration Attribution

### Why it matters

If a user has multiple email or CRM integrations, it is useful to show where the captured leads went. This increases perceived product sophistication and reinforces workflow value.

### What to show

1. Leads by integration destination
2. Top integration by volume
3. Failed or disconnected integration warning if available later

### Suggested email copy angle

"Most of your captured leads were sent to Mailchimp this month, followed by HubSpot."

### Why this helps conversion

It reminds the admin that the plugin is part of their marketing stack, not an isolated popup tool.

### Feasibility

**Available now for lead destination volume.** Reliability, sync health, and downstream performance would require extra instrumentation.

## 11. Day-of-Week and Time-of-Day Performance

### Why it matters

This is a high-value optimization report because it tells users when their campaigns perform best. It pairs naturally with scheduling features.

### What to show

1. Best day of week by conversion rate
2. Best hour block by conversion rate
3. Weakest time blocks with enough traffic
4. Suggestion to use scheduling to bias delivery toward high-performing windows

### Suggested email copy angle

"Your campaigns performed best on Tuesdays from 6pm to 9pm. Scheduling them more aggressively in those windows could raise conversions."

### Why this drives upgrades

This is one of the cleanest ways to connect analytics to a Pro feature like advanced schedules.

### Feasibility

**Needs a new aggregation/reporting layer, but the raw timestamps already exist.**

## 12. Page-Level Performance

### Why it matters

Admins need to know which pages produce results. A campaign may look weak overall but perform very well on a few high-intent pages.

### What to show

1. Top pages by campaign impressions
2. Top pages by lead conversion rate
3. Pages with strong traffic but weak conversion
4. Suggested page-specific targeting opportunities

### Suggested email copy angle

"Your /pricing page had one of the highest campaign view counts but below-average conversions. A page-specific offer could unlock more leads there."

### Why this drives upgrades

This makes targeted display rules and page-level personalization easier to sell.

### Feasibility

**Needs better impression-side path tracking.** Current click goals store a path, but impression path reporting is not fully modeled for this use case.

## 13. Funnel Drop-Off Report

### Why it matters

A compact funnel is powerful in email because it tells a clear story.

Example:

1. 12,400 views
2. 642 clicks
3. 201 leads
4. 19 purchases

This immediately shows where the largest leak is.

### Why this drives upgrades

Users can connect the leak to Pro features: stronger templates, more display rules, better mobile design, advanced triggers, or testing.

### Feasibility

**Partially available now.** Views, conversions, leads, and purchases exist, but exact step-to-step funnels may need cleaner linking.

## 14. Social Engagement Quality

### Why it matters

If the plugin includes social blocks, platform-level click breakdown can show what audience behavior looks like beyond the primary CTA.

### What to show

1. Total social interactions
2. Top clicked platform
3. Social interaction share by campaign

### Why this helps

This is not a primary KPI, but it adds depth and helps certain users understand where engagement intent is going.

### Feasibility

**Available now for tracked social clicks.**

## 15. Visitor and Pageview Momentum

### Why it matters

This is not a direct plugin KPI, but it provides business context. If traffic is growing and plugin conversions are flat, the email can say optimization is lagging traffic growth. If both are growing, the email can say the plugin is scaling with the site.

### What to show

1. Visitors this period
2. Pageviews this period
3. Trend vs previous period
4. Campaign conversion rate relative to traffic growth

### Why this helps conversion

It makes the report feel broader and more strategic.

### Feasibility

**Available now in weekly form.** Monthly would need a similar monthly rollup mechanism.

## Which Metrics Are Most Persuasive for Upgrading Free Users

If the goal is specifically **free -> Pro conversion**, these are the highest-leverage metrics in rank order:

1. **Missed opportunity estimate**
2. **Revenue influenced**
3. **Leads captured**
4. **Conversion rate and trend**
5. **Top performer vs underperformer**
6. **Mobile vs desktop performance gap**
7. **Day/time performance for scheduling**
8. **Page-level performance for targeting**
9. **Geographic performance split**
10. **Funnel drop-off**

The reason missed opportunity ranks first is simple: it does not just report performance. It quantifies why staying on free is costly.

## Recommended Weekly Email Structure

Weekly email should be short, outcome-focused, and optimization-oriented.

### Above the fold

1. Leads captured
2. Conversion rate
3. Revenue influenced
4. Missed opportunity estimate

### Middle section

1. Best-performing campaign
2. Underperforming high-traffic campaign
3. Mobile vs desktop gap

### Bottom section

1. 1 or 2 actionable recommendations
2. Pro CTA tied to the specific weakness shown in the data

### Example recommendation blocks

1. "Your mobile traffic is underperforming. Upgrade to unlock more precise campaign optimization."
2. "Your highest-traffic campaign is converting far below your best one. Upgrade to test and improve it faster."
3. "Your best results happen in specific time windows. Upgrade to schedule campaigns more strategically."

## Recommended Monthly Email Structure

Monthly email should be more strategic and summary-driven.

### Section 1: Executive summary

1. Leads
2. Conversion rate
3. Revenue influenced
4. Traffic growth

### Section 2: Performance analysis

1. Top 3 campaigns/widgets
2. Bottom 3 opportunities
3. Device split
4. Geo or audience summary

### Section 3: Opportunity analysis

1. Missed leads estimate
2. Missed revenue estimate
3. Best day/time findings

### Section 4: Upgrade CTA

Make the CTA data-driven, not generic. Example:

"You left an estimated 41 leads on the table this month from one underperforming high-traffic campaign. Pro is designed to help you recover that upside."

## Metrics to Avoid as Primary Heroes

These can appear as supporting metrics, but they should not be the main headline numbers.

1. Raw impressions alone
2. Raw pageviews alone
3. Generic plugin usage counts
4. Total campaigns created
5. Number of settings enabled

These are weak because they do not communicate business impact clearly.

## Recommended Implementation Phases

## Phase 1: Best first release

Build the email using metrics that are already realistic with current data:

1. Leads captured
2. Conversions
3. Conversion rate
4. Revenue influenced
5. Top campaign
6. Device impression split
7. Geo summary
8. Weekly traffic momentum

## Phase 2: Stronger conversion release

Add derived metrics that make the upgrade case stronger:

1. Best vs worst performer
2. Missed opportunity estimate
3. Device conversion gap
4. Country-level conversion gap
5. Integration attribution summary

## Phase 3: Premium-grade reporting

Add deeper analytics that map directly to Pro positioning:

1. Page-level performance
2. Time-of-day and day-of-week performance
3. Full funnel drop-off
4. Offer-level analytics such as coupon copy and redemption
5. Segment performance by traffic source and audience rule

## Practical Product Positioning Notes

For a free user conversion email, the report should do three jobs at once:

1. prove value already created,
2. diagnose a visible problem,
3. connect that problem to a paid solution.

That means each metric block should end with one short implication. Examples:

1. "Most of your traffic is mobile, but mobile converts worse than desktop."
2. "One campaign drives traffic but not results."
3. "Your best audience segment is clear, but you are still showing generic messaging."
4. "A small improvement in one campaign could create meaningful extra leads."

Without that implication, a stat is just information. With it, the stat becomes a conversion asset.

## Final Recommendation

If only one analytics philosophy should guide this email, use this:

**Show value created, show value missed, then show the upgrade as the shortest path to recovering that missed value.**

The best overall analytics set for that purpose is:

1. **Leads captured**
2. **Conversion rate**
3. **Revenue influenced**
4. **Top campaign vs weakest high-traffic campaign**
5. **Mobile vs desktop gap**
6. **Geo or audience opportunity**
7. **Trend vs previous period**
8. **Missed opportunity estimate**

If the team wants the most persuasive single addition beyond current reporting, it is this:

**Add a missed-opportunity model and use it in every weekly/monthly self-email.**

That one metric will likely do more for Pro conversion than adding several more passive dashboard charts.
