#pragma semicolon 1

#define PLUGIN_AUTHOR "RumbleFrog, SourceBans++ Dev Team"
#define PLUGIN_VERSION "1.6.3-rc.1"

#include <sourcemod>
#include <SteamWorks>
#include <smjansson>

#pragma newdecls required

enum
{
	Ban,
	Report,
	Type_Count
};

ConVar Convars[Type_Count];

char sEndpoints[Type_Count][256], sHostname[64], sHost[64];

public Plugin myinfo = 
{
	name = "SourceBans++ Discord Plugin",
	author = PLUGIN_AUTHOR,
	description = "Listens for ban & report forward and sends it to webhook endpoints",
	version = PLUGIN_VERSION,
	url = "https://sbpp.github.io"
};

public void OnPluginStart()
{
	CreateConVar("sbpp_discord_version", PLUGIN_VERSION, "SBPP Discord Version", FCVAR_REPLICATED | FCVAR_SPONLY | FCVAR_DONTRECORD | FCVAR_NOTIFY);
	
	Convars[Ban] = CreateConVar("sbpp_discord_banhook", "", "Discord web hook endpoint for ban forward", FCVAR_NONE);
	Convars[Report] = CreateConVar("sbpp_discord_reporthook", "", "Discord web hook endpoint for report forward. If left empty, the ban endpoint will be used instead", FCVAR_NONE);

	FindConVar("hostname").GetString(sHostname, sizeof sHostname);
	
	int iIPB = FindConVar("hostip").IntValue;
	Format(sHost, sizeof sHost, "%d.%d.%d.%d:%d", iIPB >> 24 & 0x000000FF, iIPB >> 16 & 0x000000FF, iIPB >> 8 & 0x000000FF, iIPB & 0x000000FF, FindConVar("hostport").IntValue);

	Convars[Ban].AddChangeHook(OnConvarChanged);
	Convars[Report].AddChangeHook(OnConvarChanged);
}

void SendReport(int iClient, int iTarget, const char[] sReason, int iTime = -1)
{
	if (!IsValidClient(iClient))
		return;
		
	if (iTarget != -1 && !IsValidClient(iTarget))
		return;
		
	char sAuthor[MAX_NAME_LENGTH], sTarget[MAX_NAME_LENGTH], sAuthorID[32], sAuthorID64[32], sTargetID[32], sJson[2048], sBuffer[256];
	
	GetClientName(iClient, sAuthor, sizeof sAuthor);
	GetClientAuthId(iClient, AuthId_Steam2, sAuthorID, sizeof sAuthorID);
	GetClientAuthId(iTarget, AuthId_SteamID64, sAuthorID64, sizeof sAuthorID64);
	
	GetClientName(iTarget, sTarget, sizeof sTarget);
	GetClientAuthId(iTarget, AuthId_Steam2, sTargetID, sizeof sTargetID);
	
	Handle jRequest = json_object();
	
	Handle jEmbeds = json_array();
	
	
	Handle jContent = json_object();
	
	json_object_set(jContent, "description", json_string("New Forward"));
	json_object_set(jContent, "color", json_integer(1402304));
	
	Handle jContentAuthor = json_object();
	
	json_object_set_new(jContentAuthor, "name", json_string(sAuthor));
	Format(sBuffer, sizeof sBuffer, "https://steamcommunity.com/profiles/%s", sAuthorID64);
	json_object_set_new(jContentAuthor, "url", json_string(sBuffer));
	json_object_set_new(jContent, "author", jContentAuthor);
	
	
	Handle jFields = json_array();
	
	
	Handle jFieldAuthor = json_object();
	json_object_set_new(jFieldAuthor, "name", json_string("Reporter"));
	Format(sBuffer, sizeof sBuffer, "%s (%s)", sAuthor, sAuthorID);
	json_object_set_new(jFieldAuthor, "value", json_string(sBuffer));
	json_object_set_new(jFieldAuthor, "inline", json_boolean(true));
	
	Handle jFieldTarget = json_object();
	json_object_set_new(jFieldTarget, "name", json_string("Target"));
	Format(sBuffer, sizeof sBuffer, "%s (%s)", sTarget, sTargetID);
	json_object_set_new(jFieldTarget, "value", json_string(sBuffer));
	json_object_set_new(jFieldTarget, "inline", json_boolean(true));
	
	Handle jFieldServer = json_object();
	json_object_set_new(jFieldServer, "name", json_string("Server"));
	Format(sBuffer, sizeof sBuffer, "%s (%s)", sHostname, sHost);
	json_object_set_new(jFieldServer, "value", json_string(sBuffer));
	json_object_set_new(jFieldServer, "inline", json_boolean(true));
	
	Handle jFieldReason = json_object();
	json_object_set_new(jFieldReason, "name", json_string("Reason"));
	json_object_set_new(jFieldReason, "value", json_string(sReason));
	
	json_array_append_new(jFields, jFieldAuthor);
	json_array_append_new(jFields, jFieldTarget);
	json_array_append_new(jFields, jFieldServer);
	json_array_append_new(jFields, jFieldReason);
	
	
	json_object_set_new(jContent, "fields", jFields);
	
	
	
	json_array_append_new(jEmbeds, jContent);
	json_object_set_new(jRequest, "embeds", jEmbeds);
	
	
	
	json_dump(jRequest, sJson, sizeof sJson, 0, false, false, true);
	
	#if defined DEBUG
		PrintToServer(sJson);
	#endif
	
	CloseHandle(jRequest);
	
	Handle hRequest = SteamWorks_CreateHTTPRequest(k_EHTTPMethodPOST, sHook);
	SteamWorks_SetHTTPRequestContextValue(hRequest, iClient, iTarget);
	SteamWorks_SetHTTPRequestGetOrPostParameter(hRequest, "payload_json", sJson);
	SteamWorks_SetHTTPCallbacks(hRequest, OnHTTPRequestComplete);
	
	if (!SteamWorks_SendHTTPRequest(hRequest))
		LogError("HTTP request failed for %s against %s", sAuthor, sTarget);
}

public void OnConvarChanged(ConVar convar, const char[] oldValue, const char[] newValue)
{
	if (convar == Convars[Ban])
		Convars[Ban].GetString(sEndpoints[Ban], sizeof sEndpoints[]);
	else if (convar == Convars[Report])
		Convars[Report].GetString(sEndpoints[Report], sizeof sEndpoints[]);
}