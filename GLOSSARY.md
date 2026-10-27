# Simon Center CRM

Clinic management system for patient onboarding, appointment scheduling, electronic medical records (EMR), chiropractic/traditional medicine treatments, billing, and prepaid packages.

## Language

**Patient**:
A client receiving medical care or therapy at the clinic.
_Avoid_: Customer, user, client

**Appointment**:
A scheduled clinic visit slot assigned to a patient, practitioner, and time window.
_Avoid_: Booking, event, schedule

**Medical Session**:
A discrete clinical consultation or treatment event recorded in the electronic medical record.
_Avoid_: Visit, checkup, examination

**Treatment**:
A specific therapeutic procedure or care plan executed during a session (e.g. Chiropractic, Acupuncture, Physical Therapy).
_Avoid_: Therapy, service

**Invoice**:
A financial transaction record billing for treatments, medicines, or package purchases.
_Avoid_: Bill, receipt, check

**Patient Package**:
A prepaid bundle of treatment sessions purchased by a patient and deducted per visit.
_Avoid_: Subscription, bundle, plan

**Coin System**:
A clinic credit balance system allowing patients to accumulate, top up, and redeem points for clinic services.
_Avoid_: Loyalty points, token

**Net Payable**:
The final out-of-pocket amount a patient must pay after deducting discounts and prepaid packages/coins (`max(0, subtotal - discount_amount - package_deduct)`).
_Avoid_: Total bill, gross amount

**Payment Split**:
The distribution of collected funds across payment channels (Cash, Personal Transfer, Company Transfer, Card, Debt), strictly bounded by Net Payable.
_Avoid_: Payment method, tender type

