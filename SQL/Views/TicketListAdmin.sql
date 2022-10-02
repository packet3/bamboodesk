ALTER VIEW td_TicketListAdmin AS
SELECT d.name as dname,t.did, p.name as pname, p.icon_regular, p.icon_assigned, t.priority, s.status_name, t.status,
       g.gname, u.name as uname, t.email, t.uid, t.date, t.last_reply, a.uid as userAssgined
FROM td_tickets t
         LEFT JOIN td_departments d ON t.did = d.id
         LEFT JOIN td_priorities p ON t.priority = p.id
         LEFT JOIN td_ticket_statuses s ON t.status = s.id
         LEFT JOIN td_tickets_guests g ON t.id = g.id
         LEFT JOIN td_users u ON t.id = u.id
         LEFT JOIN td_assign_map a ON t.id = a.tid;