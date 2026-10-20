import assert from 'node:assert/strict'
import { test } from 'node:test'
import { socialLinks, twitterHandle } from '../app/utils/social.ts'

test('socialLinks segue a ordem do rodapé e ignora as vazias', () => {
  const links = socialLinks({ booking: 'https://booking.com/x', instagram: 'https://www.instagram.com/p/', facebook: '' })
  assert.deepEqual(links.map(l => l.key), ['instagram', 'booking'])
  assert.equal(links[0]!.url, 'https://www.instagram.com/p/')
  assert.deepEqual(socialLinks(null), [])
})

test('twitterHandle', () => {
  assert.equal(twitterHandle('https://x.com/pousada'), '@pousada')
  assert.equal(twitterHandle('https://twitter.com/pousada_sol/status/1'), '@pousada_sol')
  assert.equal(twitterHandle('https://instagram.com/pousada'), null)
  assert.equal(twitterHandle(null), null)
})
