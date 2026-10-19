import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  availableSections, DEFAULT_LAYOUT, fromLayoutParam, layoutWarnings, moveSection, normalizeLayout,
  presetLayout, sameLayout, shownSections, toLayoutParam,
} from '../app/utils/homeLayout.ts'

const all = availableSections({ story: 3, accommodations: 4, photos: 9, extras: 7 })
const keys = (s: { key: string }[]) => s.map(x => x.key)

describe('normalizeLayout', () => {
  it('defaults to the classic home', () => {
    assert.deepEqual(shownSections(normalizeLayout(null), all), ['hero', 'story', 'stays'])
  })

  it('keeps the cover first and visible, drops unknown and repeated keys, appends the missing ones hidden', () => {
    const s = normalizeLayout([
      { key: 'stays', visible: true }, { key: 'hero', visible: false }, { key: 'banner', visible: true }, { key: 'stays', visible: false },
    ])
    assert.deepEqual(keys(s), ['hero', 'stays', 'story', 'intro', 'gallery', 'extras'])
    assert.equal(s[0]!.visible, true)
    assert.deepEqual(shownSections(s, all), ['hero', 'stays'])
  })
})

describe('preview param', () => {
  it('round-trips the visible order', () => {
    const s = presetLayout(['hero', 'gallery', 'stays'])
    assert.equal(toLayoutParam(s), 'hero,gallery,stays')
    assert.ok(sameLayout(fromLayoutParam('hero,gallery,stays')!, s))
  })

  it('ignores garbage', () => {
    assert.equal(fromLayoutParam('nada,disso'), null)
    assert.equal(fromLayoutParam(['hero']), null)
  })
})

describe('shownSections', () => {
  it('skips sections without content', () => {
    const empty = availableSections({ story: 0, accommodations: 2, photos: 1, extras: 0 })
    assert.deepEqual(shownSections(presetLayout(['hero', 'story', 'gallery', 'stays', 'extras']), empty), ['hero', 'stays'])
  })
})

describe('moveSection', () => {
  it('reorders but never moves anything above the cover', () => {
    const moved = moveSection(DEFAULT_LAYOUT, 2, 1)
    assert.deepEqual(keys(moved).slice(0, 3), ['hero', 'stays', 'story'])
    assert.equal(moveSection(DEFAULT_LAYOUT, 1, 0), DEFAULT_LAYOUT)
    assert.equal(moveSection(DEFAULT_LAYOUT, 0, 2), DEFAULT_LAYOUT)
  })
})

describe('layoutWarnings', () => {
  it('warns when the accommodations disappear or sink to the bottom', () => {
    assert.equal(layoutWarnings(presetLayout(['hero', 'story']), all).length, 1)
    assert.equal(layoutWarnings(presetLayout(['hero']), all).length, 2)
    assert.equal(layoutWarnings(presetLayout(['hero', 'intro', 'story', 'gallery', 'stays']), all).length, 1)
    assert.deepEqual(layoutWarnings(DEFAULT_LAYOUT, all), [])
  })
})
