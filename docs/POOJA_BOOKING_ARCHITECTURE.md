# Pooja Booking System - Architecture

## Domain Model

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              BOOKING (Receipt)                               │
│  booking_number, contact_name, contact_number, total, paid, balance, status │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                    ┌─────────────────┼─────────────────┐
                    ▼                                   ▼
        ┌───────────────────────┐           ┌───────────────────────┐
        │    BOOKING_ITEMS      │           │   BOOKING_PAYMENTS    │
        │  pooja, deity, dates  │           │  amount, method, date │
        │  frequency, amount    │           └───────────────────────┘
        └───────────────────────┘
                    │
        ┌───────────┼───────────┐
        ▼                       ▼
┌─────────────────┐    ┌─────────────────────┐
│  BENEFICIARIES  │    │  BOOKING_SCHEDULES  │
│  name, naksha-  │    │  scheduled_date,    │
│  thra, gothram  │    │  status, completed  │
└─────────────────┘    └─────────────────────┘
```

## Core Entities

### 1. Booking (Receipt Master)
The main transaction record - one receipt per booking.
- Unique booking number per temple (TEMPLE_CODE/YYYY/NNNNNN)
- Contact details (mandatory phone if not fully paid)
- Payment summary (total, paid, balance)
- Status tracking

### 2. BookingItem
Individual pooja selections within a booking.
- Links to Pooja and Deity
- Date range for recurring poojas
- Frequency (once, daily, weekly, monthly)
- Amount calculation

### 3. BookingBeneficiary
People for whom the pooja is performed.
- Name is mandatory
- Nakshathra (star sign) - for daily/monthly automation later
- Gothram (optional)
- Each beneficiary gets the pooja performed

### 4. BookingSchedule
Actual scheduled occurrences for daily operations.
- One row per date per booking item
- Status: pending, completed, cancelled
- This is what temple staff sees daily

### 5. BookingPayment
Payment records against booking.
- Multiple payments allowed (partial payments)
- Different payment methods
- Reference tracking

## Recurrence Strategy

### Generation Approach
When a booking item is created:
1. **Once**: Create 1 schedule for the pooja date
2. **Daily**: Generate schedules for each day in date range
3. **Weekly**: Generate schedules for each week (same weekday)
4. **Monthly**: Generate schedules for each month

### Why Pre-generate?
- Daily view is instant (no calculation needed)
- Easy to mark completion/cancellation
- Simple reporting
- Cron can process pending schedules

### Monthly Recurrence Options
1. **By Date**: Same date each month (e.g., 15th)
2. **By Nakshathra**: Same star day each month (future automation)

## Payment Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   BOOKING   │────▶│   PAYMENT   │────▶│   UPDATE    │
│   CREATED   │     │   RECEIVED  │     │   BALANCE   │
└─────────────┘     └─────────────┘     └─────────────┘
       │                                       │
       │                                       ▼
       │                              ┌─────────────────┐
       │                              │ balance = 0?    │
       │                              │ status = 'paid' │
       │                              └─────────────────┘
       ▼
┌─────────────────┐
│ Contact Number  │
│ Required if     │
│ balance > 0     │
└─────────────────┘
```

## Daily Operations Flow

### Morning View
```sql
SELECT bs.*, bi.*, b.*, p.name as pooja_name, d.name as deity_name
FROM booking_schedules bs
JOIN booking_items bi ON bs.booking_item_id = bi.id
JOIN bookings b ON bi.booking_id = b.id
JOIN poojas p ON bi.pooja_id = p.id
JOIN deities d ON bi.deity_id = d.id
WHERE bs.scheduled_date = CURDATE()
  AND bs.status = 'pending'
ORDER BY p.id, d.id, bs.id
```

### Completion Flow
1. Staff opens Daily Poojas page
2. Sees all pending poojas grouped by type
3. Expands to see beneficiaries
4. Marks individual or batch completion
5. System records completion time and user

## Accounting Integration

### Simple Tracking
- Daily Collection Report: Sum of payments by date and method
- Outstanding Report: Bookings with balance > 0
- Pooja Revenue Report: By pooja type and period

### Future: Double-Entry Ledger
- Cash/Bank Dr → Pooja Income Cr
- Receivables tracking
- GST handling if needed

## API Endpoints

### Bookings
- `POST /api/bookings` - Create new booking with items
- `GET /api/bookings` - List bookings with filters
- `GET /api/bookings/{id}` - Booking details with items
- `PUT /api/bookings/{id}` - Update booking
- `DELETE /api/bookings/{id}` - Cancel booking

### Payments
- `POST /api/bookings/{id}/payments` - Add payment
- `GET /api/bookings/{id}/payments` - Payment history

### Daily Operations
- `GET /api/daily-poojas?date=YYYY-MM-DD` - Get day's schedule
- `POST /api/booking-schedules/{id}/complete` - Mark complete
- `POST /api/daily-poojas/batch-complete` - Batch complete

### Reports
- `GET /api/reports/daily-collection` - Daily collection
- `GET /api/reports/outstanding` - Outstanding payments
- `GET /api/reports/pooja-summary` - Pooja-wise summary

## UI Pages

1. **Booking Form** - Multi-step wizard
   - Step 1: Contact Details
   - Step 2: Select Poojas (add items)
   - Step 3: Add Beneficiaries per item
   - Step 4: Payment & Confirmation

2. **Booking List** - With filters and search

3. **Booking Detail** - View/edit booking, add payments

4. **Daily Poojas** - Staff daily view
   - Group by Pooja Type
   - Expand to see beneficiaries
   - Mark completion

5. **Reports Dashboard**
   - Collection summary
   - Outstanding list
   - Pooja statistics
