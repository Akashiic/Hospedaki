// Regras do calendário de diárias e do "hoje" no fuso da pousada.
// Roda com o executor de testes do próprio Node: `npm test`.
import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { canCheckOut, dayNotes, dayState, isSelectable, maxMonthOffset, monthGrid, pickDay, rangeIsFree } from '../app/utils/calendar.ts'
import type { CalendarState } from '../app/utils/calendar.ts'
import { addMonthsIso, monthsBetween, todayIn } from '../app/utils/dates.ts'

// Hoje 2026-10-20; noites ocupadas 24 e 25 (alguém fica de 24 a 26).
const base: CalendarState = {
  today: '2026-10-20',
  checkIn: '',
  checkOut: '',
  blocked: new Set(['2026-10-24', '2026-10-25']),
  maxDate: '2027-10-20',
}
const choosingFrom = (checkIn: string, extra: Partial<CalendarState> = {}): CalendarState => ({ ...base, checkIn, ...extra })

describe('rangeIsFree', () => {
  it('olha só as noites da entrada até a véspera da saída', () => {
    assert.equal(rangeIsFree('2026-10-21', '2026-10-24', base.blocked), true)
    assert.equal(rangeIsFree('2026-10-21', '2026-10-25', base.blocked), false)
    assert.equal(rangeIsFree('2026-10-26', '2026-10-28', base.blocked), true)
  })
})

describe('saída', () => {
  it('pode cair num dia ocupado quando as noites antes estão livres', () => {
    const s = choosingFrom('2026-10-22')
    assert.equal(canCheckOut('2026-10-24', s), true)
    assert.equal(isSelectable('2026-10-24', s), true)
    assert.deepEqual(pickDay('2026-10-24', s), { checkIn: '2026-10-22', checkOut: '2026-10-24' })
    assert.deepEqual(dayNotes('2026-10-24', s), ['ocupado, disponível só para saída'])
  })

  it('não passa por cima de uma noite ocupada', () => {
    const s = choosingFrom('2026-10-22')
    assert.equal(canCheckOut('2026-10-25', s), false)
    assert.equal(canCheckOut('2026-10-27', s), false)
  })

  it('um dia livre depois de noites ocupadas vira nova entrada, e o rótulo avisa', () => {
    const s = choosingFrom('2026-10-22')
    assert.equal(isSelectable('2026-10-27', s), true)
    assert.deepEqual(pickDay('2026-10-27', s), { checkIn: '2026-10-27', checkOut: '' })
    assert.deepEqual(dayNotes('2026-10-27', s), ['há noites ocupadas antes; escolher este dia começa uma nova entrada'])
  })
})

describe('entrada', () => {
  it('um dia ocupado não pode ser entrada', () => {
    assert.equal(isSelectable('2026-10-24', base), false)
    assert.equal(pickDay('2026-10-24', base), null)
    assert.equal(dayState('2026-10-24', base), 'blocked')
    assert.deepEqual(dayNotes('2026-10-24', base), ['indisponível'])
  })

  it('dias passados são recusados; hoje vale', () => {
    assert.equal(isSelectable('2026-10-19', base), false)
    assert.equal(dayState('2026-10-19', base), 'past')
    assert.equal(isSelectable('2026-10-20', base), true)
  })
})

describe('fim da janela buscada (maxDate)', () => {
  it('recusa entrada a partir do maxDate e marca "agenda ainda não aberta"', () => {
    assert.equal(isSelectable('2027-10-19', base), true)
    assert.equal(isSelectable('2027-10-20', base), false)
    assert.equal(isSelectable('2027-11-02', base), false)
    assert.equal(dayState('2027-10-20', base), 'closed')
    assert.deepEqual(dayNotes('2027-11-02', base), ['agenda ainda não aberta'])
  })

  it('aceita saída até o próprio maxDate, não depois', () => {
    const s = choosingFrom('2027-10-17')
    assert.equal(canCheckOut('2027-10-20', s), true)
    assert.equal(dayState('2027-10-20', s), 'free')
    assert.equal(canCheckOut('2027-10-21', s), false)
  })

  it('para de paginar no mês do maxDate (dois meses na tela)', () => {
    assert.equal(maxMonthOffset('2026-10-20', '2027-10-20'), 11)
    assert.equal(maxMonthOffset('2026-10-20', '2026-10-30'), 0)
    assert.equal(maxMonthOffset('2026-10-20'), Number.POSITIVE_INFINITY)
  })
})

describe('grade do mês', () => {
  it('começa no dia da semana certo e tem todos os dias', () => {
    const october = monthGrid('2026-10-20', 0)
    // 1º de outubro de 2026 é quinta-feira: 4 espaços (D, S, T, Q) antes.
    assert.deepEqual(october.days.slice(0, 5), [null, null, null, null, '2026-10-01'])
    assert.equal(october.days.at(-1), '2026-10-31')
    assert.equal(october.label, 'outubro de 2026')
    assert.equal(monthGrid('2026-10-20', 4).days.at(-1), '2027-02-28')
  })
})

describe('datas', () => {
  it('hoje no fuso da pousada, não no do servidor ou do navegador', () => {
    // 00:30 UTC do dia 24 ainda é 21:30 do dia 23 em Brasília (mesmo caso do BookingRequestTest).
    const now = new Date('2026-10-24T00:30:00Z')
    assert.equal(todayIn('America/Sao_Paulo', now), '2026-10-23')
    assert.equal(todayIn('UTC', now), '2026-10-24')
    assert.equal(todayIn('America/Rio_Branco', new Date('2026-10-24T04:30:00Z')), '2026-10-23')
  })

  it('soma meses sem pular para o mês seguinte', () => {
    assert.equal(addMonthsIso('2026-10-20', 12), '2027-10-20')
    assert.equal(addMonthsIso('2027-01-31', 1), '2027-02-28')
    assert.equal(monthsBetween('2026-10-20', '2027-01-05'), 3)
  })
})

describe('single day (hourly)', () => {
  it('picks one day and never a check-out', async () => {
    const { pickDay, dayNotes } = await import('../app/utils/calendar.ts')
    const s = { today: '2026-10-20', checkIn: '2026-10-25', checkOut: '', blocked: new Set<string>(), single: true }
    assert.deepEqual(pickDay('2026-10-27', s), { checkIn: '2026-10-27', checkOut: '' })
    assert.deepEqual(dayNotes('2026-10-25', s), ['dia escolhido'])
  })
})
