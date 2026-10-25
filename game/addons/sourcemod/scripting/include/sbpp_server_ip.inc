// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2026 SourceBans++ Dev Team <https://github.com/sbpp>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
// *************************************************************************

#if defined _sbpp_server_ip_included
#endinput
#endif
#define _sbpp_server_ip_included

#define SBPP_SERVER_IP_LENGTH 16

/**
 * SDR / Steam Networking ("FakeIP") clients are not reachable by a real address.
 * The engine hands the server a placeholder instead. From the Source SDK 2013
 * Steamworks headers (steamnetworkingtypes.h):
 *
 *   FakeIP addresses are designed to (hopefully) pass through existing code as
 *   transparently as possible, while conflicting with "real" addresses that might
 *   be in use on networks (both the Internet and LANs) in the same code as little
 *   as possible.  At the time this comment is being written, they come from the
 *   169.254.0.0/16 range, and the port number will always be >1024.  HOWEVER,
 *   this is subject to change!  Do not make assumptions about these addresses,
 *   or your code might break in the future.  In particular, you should use
 *   functions such as ISteamNetworkingUtils::IsFakeIP to determine if an IP
 *   address is a "fake" one used by this system.
 *
 *   https://github.com/ValveSoftware/source-sdk-2013/blob/be57e3d01ac7d7aa6a2cacbfef187c0b46616db0/src/public/steam/isteamnetworkingsockets.h#L812-L820
 *
 *
 * ISteamNetworkingUtils::IsFakeIP is a C++ interface that SourceMod does not
 * expose to SourcePawn, so SourceBans++ falls back to the 169.254.0.0/16 range
 * described above. If a native for IsFakeIP ever becomes available, this is
 * the single place to switch to it.
 *
 * @param ip    Client IP address string (without port).
 * @return      True if the address is a Steam Networking FakeIP.
 */
stock bool SBPP_IsSteamNetworkingIP(const char[] ip)
{
	return strncmp(ip, "169.254.", 8) == 0;
}

/**
 * GetClientIP wrapper that never returns a Steam Networking FakeIP, so callers
 * can't store or match it by accident. A FakeIP is shared by every SDR client
 * and says nothing about the player.
 *
 * @param client    Client index.
 * @param buffer    Buffer for the IP (port removed). Emptied when unavailable.
 * @param maxlen    Size of buffer.
 * @return          True if buffer holds a usable IP, false if it is empty
 *                  (lookup failed or the address is a FakeIP).
 */
stock bool SBPP_GetClientPublicIP(int client, char[] buffer, int maxlen)
{
	if (!GetClientIP(client, buffer, maxlen, true) || SBPP_IsSteamNetworkingIP(buffer))
	{
		buffer[0] = '\0';
		return false;
	}

	return true;
}

stock bool SBPP_IsValidServerIpOverride(const char[] ip)
{
	if (ip[0] == '\0')
	{
		return true;
	}

	char octets[5][16];
	if (ExplodeString(ip, ".", octets, sizeof(octets), sizeof(octets[])) != 4)
	{
		return false;
	}

	for (int octet = 0; octet < 4; octet++)
	{
		int length = strlen(octets[octet]);
		if (length == 0 || length > 3)
		{
			return false;
		}

		for (int character = 0; character < length; character++)
		{
			if (!IsCharNumeric(octets[octet][character]))
			{
				return false;
			}
		}

		if (StringToInt(octets[octet]) > 255)
		{
			return false;
		}
	}

	return true;
}

stock void SBPP_ResolveServerIp(ConVar hostIp, const char[] configuredIp, char[] output, int maxLength)
{
	if (configuredIp[0] != '\0')
	{
		strcopy(output, maxLength, configuredIp);
		return;
	}

	int pieces[4];
	int longIp = hostIp.IntValue;

	pieces[0] = (longIp >> 24) & 0x000000FF;
	pieces[1] = (longIp >> 16) & 0x000000FF;
	pieces[2] = (longIp >> 8) & 0x000000FF;
	pieces[3] = longIp & 0x000000FF;

	FormatEx(output, maxLength, "%d.%d.%d.%d", pieces[0], pieces[1], pieces[2], pieces[3]);
}
