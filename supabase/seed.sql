begin;

-- Fictional catalog for demonstrations. Run only where sample resources belong.
insert into public.rooms(id,name,location,description,capacity,chairs,tables_count,photo,layout) values
('a1000000-0000-4000-8000-000000000001','Seminar Room A','Innovation Center · Level 2',
 'A tiered seminar space with a presentation wall and flexible seating for talks, panels, and committee events.',80,80,20,'seminar',
 '{"width":14,"depth":12,"door":{"wall":"south","offset":6.4,"width":1.2,"clearance":1.2},"blocked":[],"fixed":[{"x":0.5,"y":0.5,"width":0.6,"depth":0.6,"label":"Lectern"}]}'),
('a1000000-0000-4000-8000-000000000002','Classroom B','Learning Commons · Level 1',
 'A bright, reconfigurable classroom with movable desks for workshops, training, and group discussions.',36,36,18,'classroom',
 '{"width":12,"depth":10,"door":{"wall":"south","offset":5.4,"width":1.2,"clearance":1.2},"blocked":[],"fixed":[]}'),
('a1000000-0000-4000-8000-000000000003','Multipurpose Hall C','Student Center · Ground floor',
 'An open hall for campus gatherings with an acoustic stage, wide aisles, and accessible entry.',150,150,35,'hall',
 '{"width":20,"depth":16,"door":{"wall":"south","offset":9.25,"width":1.5,"clearance":1.2},"blocked":[{"x":18,"y":0.5,"width":1.2,"depth":2,"label":"Storage"}],"fixed":[]}')
on conflict do nothing;

insert into public.room_facilities(room_id,facility)
select r.id, f.facility from (values
  ('a1000000-0000-4000-8000-000000000001','Projector'),
  ('a1000000-0000-4000-8000-000000000001','Air conditioning'),
  ('a1000000-0000-4000-8000-000000000001','Audio system'),
  ('a1000000-0000-4000-8000-000000000001','Wi-Fi'),
  ('a1000000-0000-4000-8000-000000000002','Whiteboard'),
  ('a1000000-0000-4000-8000-000000000002','Air conditioning'),
  ('a1000000-0000-4000-8000-000000000002','Wi-Fi'),
  ('a1000000-0000-4000-8000-000000000003','Audio system'),
  ('a1000000-0000-4000-8000-000000000003','Projector'),
  ('a1000000-0000-4000-8000-000000000003','Accessible entry')
) f(room_id,facility) join public.rooms r on r.id=f.room_id::uuid
on conflict do nothing;

insert into public.equipment(id,name,category,description,total_quantity) values
('a2000000-0000-4000-8000-000000000001','Extension Cable','Power','A grounded 10-metre extension reel for event power.',16),
('a2000000-0000-4000-8000-000000000002','Wireless Microphone','Audio','Handheld wireless microphone with receiver and batteries.',8),
('a2000000-0000-4000-8000-000000000003','Portable Projector','Presentation','Compact HDMI projector with a protective carry case.',5),
('a2000000-0000-4000-8000-000000000004','Portable Speaker','Audio','Rechargeable speaker for small talks and workshops.',6),
('a2000000-0000-4000-8000-000000000005','Tripod','Accessories','Adjustable tripod with a standard quick-release plate.',10),
('a2000000-0000-4000-8000-000000000006','Folding Table','Furniture','Lightweight rectangular table for check-in or group activities.',24),
('a2000000-0000-4000-8000-000000000007','Stacking Chair','Furniture','Stackable seating for flexible campus events.',80)
on conflict do nothing;

commit;
