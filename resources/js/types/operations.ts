import type { QueueStatus } from '@/types/customer';

export type StaffTicket = {
    id: string;
    queue_number: string;
    queue_date: string;
    customer_name: string;
    phone_masked: string;
    barber: { id: number; name: string };
    service: { id: number; name: string };
    source: 'online' | 'onsite';
    status: QueueStatus;
    price: string;
    duration_minutes: number;
    checked_in_at: string | null;
    called_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    grace_expires_at: string | null;
    cancellation_reason: string | null;
    actions: {
        start: boolean;
        complete: boolean;
        missed: boolean;
        cancel: boolean;
    };
    detail_url: string;
};
export type StaffBarber = {
    id: number;
    name: string;
    availability: 'available' | 'busy' | 'break' | 'off_duty';
    is_active: boolean;
    accepts_queue: boolean;
};
export type StaffStats = Record<QueueStatus | 'total' | 'waiting', number>;
export type DashboardProps = {
    date: string;
    timezone: string;
    stats: StaffStats;
    active_barbers: number;
    barber: StaffBarber | null;
    profile_missing: boolean;
    processing: StaffTicket[];
    next: StaffTicket | null;
    can_call: boolean;
    barbers: StaffBarber[];
};
export type StaffPageLink = {
    label: string;
    url: string | null;
    active: boolean;
};
export type StaffQueueProps = {
    tickets: {
        data: StaffTicket[];
        links: StaffPageLink[];
        total: number;
        current_page: number;
        last_page: number;
    };
    filters: {
        date: string;
        barber_id: string;
        source: string;
        status: string;
    };
    is_admin: boolean;
    history: boolean;
    barbers: { id: number; name: string }[];
    stats: StaffStats;
};
export type QueueAuditEvent = {
    id: number;
    type: string;
    previous_status: QueueStatus | null;
    new_status: QueueStatus;
    actor: string;
    reason: string | null;
    created_at: string;
};
