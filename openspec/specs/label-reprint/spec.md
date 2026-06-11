# Label Reprint Specification

## Purpose

Add an individual reprint action to the LabelResource table, allowing users to reprint a single label without creating a full batch print queue.

## Requirements

### Requirement: Reprint action on LabelResource

The LabelResource table MUST include a "Reimprimir" action visible per row.

#### Scenario: Reprint action visible for non-anulled labels

- GIVEN a label with `status != 'anulled'`
- WHEN viewing the LabelResource index or relation manager
- THEN a "Reimprimir" action button MUST be visible in the row's action group
- AND the button SHOULD have a printer icon and warning/primary color

#### Scenario: Reprint action hidden for anulled labels

- GIVEN a label with `status = 'anulled'`
- WHEN viewing the LabelResource index
- THEN the "Reimprimir" action MUST NOT be visible

### Requirement: Printer selection on reprint

The action MUST prompt the user to select or confirm a printer target before sending.

#### Scenario: Default printer configured

- GIVEN an active `ZebraPrintSetting` exists with a configured printer
- WHEN the user clicks "Reimprimir"
- THEN a confirmation modal SHOULD appear showing the printer endpoint
- AND the user MAY confirm to proceed without re-entering connection details

#### Scenario: No default printer

- GIVEN no active `ZebraPrintSetting` exists
- WHEN the user clicks "Reimprimir"
- THEN a modal MUST show IP and port inputs for manual Zebra address entry
- AND the inputs MUST be required before proceeding

### Requirement: Individual label ZPL generation and queuing

The reprint MUST generate ZPL for the single label and create a `PrintQueueItem` in a new or existing queue.

#### Scenario: Single label queued for reprint

- GIVEN the user confirms the reprint action
- THEN `ZebraZplService::generateZplForItem()` MUST be called for the single label
- AND a `PrintQueueItem` MUST be created with status `pending`
- AND a `LabelLog` entry MUST record the reprint action
- AND a success notification MUST be shown

#### Scenario: Zebra communication failure

- GIVEN the Zebra printer is unreachable
- WHEN the reprint action tries to send ZPL
- THEN a `PrintQueueItem` is created with status `failed`
- AND a warning notification is shown
- AND the user MAY retry via the reprint action again

### Acceptance Criteria

- [ ] "Reimprimir" action visible on non-anulled labels in LabelResource
- [ ] Action hidden on anulled labels
- [ ] Printer selection modal appears (default or manual)
- [ ] Individual ZPL generated and queued
- [ ] Audit log entry created for each reprint
- [ ] Notification shown on success or failure
