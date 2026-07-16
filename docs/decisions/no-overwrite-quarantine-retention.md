# Decision: no-overwrite quarantine retention

Quarantine writes use exclusive file creation and fail closed when the planned path already exists. Database failure after a successful write triggers compensating removal only for the object created by that operation. Retention is not approval, promotion, duplicate matching or derivative generation.
