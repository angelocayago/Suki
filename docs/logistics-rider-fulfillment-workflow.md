# Logistics and Rider fulfillment workflow

1. **Seller preparation:** The Seller prepares and marks an order ready for pickup. The shipment starts in `READY_FOR_PICKUP` with no pickup rider assigned.
2. **Logistics pickup assignment:** An approved Logistics provider assigns an eligible, active, verified provider Rider whose active hub and service area cover the Seller's pickup address. An unassigned shipment is shown as **Unassigned / Assignment Needed**.
3. **Rider acceptance:** The assigned pickup Rider accepts or declines. Until acceptance, the assignment may be changed. After `pickup_accepted_at` is set, Logistics cannot reassign that pickup in the UI or through the workflow service.
4. **Pickup and inbound movement:** The pickup Rider confirms collection, transitioning the shipment to `PICKED_UP` (Seller → Sorting Center). The inbound queue shows **En Route to Sorting Center**.
5. **Sorting-center arrival:** The pickup Rider confirms arrival at `AT_SORTING_CENTER`. Logistics receives the parcel through the existing provider-scoped scanner workflow.
6. **Verified sorting:** Logistics scans/resolves the exact signed parcel QR. After required receiving, the verified shipment may be sorted to an active provider-owned destination zone. Exact zone matches are recommended; ambiguous/unmapped destinations require an auditable override. Scanning alone never marks a parcel sorted.
7. **Delivery rider assignment:** A separate `rider_id` is assigned from eligible Riders matching the Buyer destination and applicable hub/zone. This does not replace `pickup_rider_id`.
8. **Delivery acceptance and dispatch:** The delivery Rider accepts, then advances the shipment to `OUT_FOR_DELIVERY` (Sorting Center → Buyer).
9. **Delivery and completion:** The delivery Rider records proof and delivery, transitioning to `DELIVERED`. The Buyer confirms receipt to reach `COMPLETED` (or the existing automatic completion policy applies where configured).

`pickup_rider_id` always represents Seller → Sorting Center responsibility. `rider_id` always represents Sorting Center → Buyer responsibility. Logistics queues and dashboard counts use persisted order/shipment state; session placeholders are not operational data.
