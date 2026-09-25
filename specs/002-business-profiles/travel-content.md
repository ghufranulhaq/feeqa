# Travel Launch Content (Draft: Needs Client Approval)

> This appendix to [spec 002](spec.md) (categories and question sets) and [spec 011](../011-ai-insights/spec.md) (topic taxonomy) is **draft content**. The client must approve it before planning starts. Changes after launch follow the question-set versioning rule in FR-002-20.

## 1. Category Tree (launched at go-live)

```
Travel
├── Airlines
├── Travel Agencies & OTAs
│   ├── Online Travel Agencies
│   └── High-street / Tour Agencies
└── Airports
```

The categories *Hotels*, *Car Hire*, and *Tour Operators* exist but have `launched = false`. They are not seeded.

## 2. Question Sets (context-aware prompts)

Types: `rating_1_5`, `yes_no`, `single_choice`, `short_text`. **R** = required, **O** = optional.

### Travel (parent, inherited by all)
| Key | Label | Type | R/O |
|-----|-------|------|-----|
| `booking_ease` | How easy was booking? | rating_1_5 | O |
| `value_for_money` | Value for money | rating_1_5 | O |
| `use_again` | Would you use them again? | yes_no | R |

### Airlines
| Key | Label | Type | R/O |
|-----|-------|------|-----|
| `on_time` | Punctuality (on-time departure/arrival) | rating_1_5 | R |
| `baggage` | Baggage handling | rating_1_5 | O |
| `crew` | Cabin crew | rating_1_5 | O |
| `seat_comfort` | Seat comfort | rating_1_5 | O |
| `disruption_handling` | Handling of delays/cancellations (if any) | rating_1_5 | O |
| `cabin_class` | Cabin class | single_choice: Economy / Premium Economy / Business / First | O |
| `flight_type` | Flight type | single_choice: Short-haul / Long-haul | O |

### Travel Agencies & OTAs
| Key | Label | Type | R/O |
|-----|-------|------|-----|
| `price_transparency` | Were all fees shown up front? | yes_no | R |
| `customer_service` | Customer service | rating_1_5 | O |
| `changes_cancellations` | Handling of changes/cancellations (if any) | rating_1_5 | O |
| `refund_handling` | Refund handling (if any) | rating_1_5 | O |
| `documents_on_time` | Tickets/documents received on time | yes_no | O |

### Airports
| Key | Label | Type | R/O |
|-----|-------|------|-----|
| `security_wait` | Security queue time | rating_1_5 | R |
| `cleanliness` | Cleanliness | rating_1_5 | O |
| `signage` | Ease of finding your way | rating_1_5 | O |
| `accessibility` | Accessibility / assistance services | rating_1_5 | O |
| `facilities` | Shops, food, and seating | rating_1_5 | O |

## 3. Topic Taxonomy (for AI topics, spec 011)

| Category | Topics |
|----------|--------|
| Airlines | punctuality, cancellations, baggage, crew, seating & comfort, food & drink, check-in & boarding, refunds, compensation, customer service, fees & pricing, app & website |
| Travel Agencies & OTAs | booking process, hidden fees, customer service, changes & cancellations, refunds, ticket delivery, communication, price & value |
| Airports | security, queues, cleanliness, wayfinding, accessibility & assistance, shops & food, transport links, staff |

## 4. Default Invitation Timing (spec 005)

| Category | Default send delay |
|----------|--------------------|
| Airlines | 1 day after the travel date on the transaction record (falls back to 1 day after the trigger) |
| Travel Agencies & OTAs | 1 day after the travel date (falls back to 3 days after booking) |
| Airports | Not applicable (no transaction invitations; link/QR only) |
